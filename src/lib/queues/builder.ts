import crypto from 'crypto';
import fs from 'fs/promises';
import * as buildpacks from '../buildPacks';
import * as importers from '../importers';
import { dockerInstance } from '../docker';
import { asyncExecShell, createDirectories, getDomain, getEngine, saveBuildLog } from '../common';
import { configureProxyForApplication, reloadHaproxy, setWwwRedirection } from '../haproxy';
import * as db from '$lib/database';
import { decrypt } from '$lib/crypto';
import { sentry } from '$lib/common';
import {
	copyBaseConfigurationFiles,
	makeLabelForStandaloneApplication,
	setDefaultConfiguration
} from '$lib/buildPacks/common';
import { letsEncrypt } from '$lib/letsencrypt';

export default async function (job) {
	/*
	Edge cases:
	1 - Change build pack and redeploy, what should happen?
  */
	let {
		id: applicationId,
		repository,
		branch,
		buildPack,
		name,
		destinationDocker,
		destinationDockerId,
		gitSource,
		build_id: buildId,
		configHash,
		port,
		installCommand,
		buildCommand,
		startCommand,
		fqdn,
		baseDirectory,
		publishDirectory,
		projectId,
		secrets,
		type,
		pullmergeRequestId = null,
		sourceBranch = null,
		settings
	} = job.data;
	const { debug } = settings;

	let imageId = applicationId;
	let domain = getDomain(fqdn);
	const isHttps = fqdn.startsWith('https://');

	// Previews, we need to get the source branch and set subdomain
	if (pullmergeRequestId) {
		branch = sourceBranch;
		domain = `${pullmergeRequestId}.${domain}`;
		imageId = `${applicationId}-${pullmergeRequestId}`;
	}

	let deployNeeded = true;
	let destinationType;

	if (destinationDockerId) {
		destinationType = 'docker';
	}

	if (destinationType === 'docker') {
		const docker = dockerInstance({ destinationDocker });
		const host = getEngine(destinationDocker.engine);

		const build = await db.createBuild({
			id: buildId,
			applicationId,
			destinationDockerId: destinationDocker.id,
			gitSourceId: gitSource.id,
			githubAppId: gitSource.githubApp?.id,
			gitlabAppId: gitSource.gitlabApp?.id,
			type
		});

		const { workdir, repodir } = await createDirectories({ repository, buildId: build.id });

		const configuration = await setDefaultConfiguration(job.data);

		buildPack = configuration.buildPack;
		port = configuration.port;
		installCommand = configuration.installCommand;
		startCommand = configuration.startCommand;
		buildCommand = configuration.buildCommand;
		publishDirectory = configuration.publishDirectory;

		let commit = await importers[gitSource.type]({
			applicationId,
			debug,
			workdir,
			repodir,
			githubAppId: gitSource.githubApp?.id,
			gitlabAppId: gitSource.gitlabApp?.id,
			repository,
			branch,
			buildId: build.id,
			apiUrl: gitSource.apiUrl,
			projectId,
			deployKeyId: gitSource.gitlabApp?.deployKeyId || null,
			privateSshKey: decrypt(gitSource.gitlabApp?.privateSshKey) || null
		});
		let tag = commit.slice(0, 7);
		if (pullmergeRequestId) {
			tag = `${commit.slice(0, 7)}-${pullmergeRequestId}`;
		}

		try {
			await db.prisma.build.update({ where: { id: build.id }, data: { commit } });
		} catch (err) {
			console.log(err);
		}

		if (!pullmergeRequestId) {
			const currentHash = crypto
				.createHash('sha256')
				.update(
					JSON.stringify({
						buildPack,
						port,
						installCommand,
						buildCommand,
						startCommand,
						secrets,
						branch,
						repository,
						fqdn
					})
				)
				.digest('hex');

			if (configHash !== currentHash) {
				await db.prisma.application.update({
					where: { id: applicationId },
					data: { configHash: currentHash }
				});
				deployNeeded = true;
				if (configHash) {
					saveBuildLog({ line: 'Configuration changed.', buildId, applicationId });
				}
			} else {
				deployNeeded = false;
			}
		} else {
			deployNeeded = true;
		}
		const image = await docker.engine.getImage(`${applicationId}:${tag}`);

		let imageFound = false;
		try {
			await image.inspect();
			imageFound = false;
		} catch (error) {
			//
		}
		if (!imageFound || deployNeeded) {
			await copyBaseConfigurationFiles(buildPack, workdir, buildId, applicationId);
			if (buildpacks[buildPack])
				await buildpacks[buildPack]({
					buildId: build.id,
					applicationId,
					domain,
					name,
					type,
					pullmergeRequestId,
					buildPack,
					repository,
					branch,
					projectId,
					publishDirectory,
					debug,
					commit,
					tag,
					workdir,
					docker,
					port,
					installCommand,
					buildCommand,
					startCommand,
					baseDirectory,
					secrets
				});
			else {
				saveBuildLog({ line: `Build pack ${buildPack} not found`, buildId, applicationId });
				throw new Error(`Build pack ${buildPack} not found.`);
			}
			deployNeeded = true;
		} else {
			deployNeeded = false;
			saveBuildLog({ line: 'Nothing changed.', buildId, applicationId });
		}

		// Deploy to Docker Engine
		try {
			await asyncExecShell(`DOCKER_HOST=${host} docker stop -t 0 ${imageId}`);
			await asyncExecShell(`DOCKER_HOST=${host} docker rm ${imageId}`);
		} catch (error) {
			//
		}
		const envs = [];
		if (secrets.length > 0) {
			secrets.forEach((secret) => {
				envs.push(`${secret.name}=${secret.value}`);
			});
		}
		await fs.writeFile(`${workdir}/.env`, envs.join('\n'));
		const labels = makeLabelForStandaloneApplication({
			applicationId,
			fqdn,
			name,
			type,
			pullmergeRequestId,
			buildPack,
			repository,
			branch,
			projectId,
			port,
			commit,
			installCommand,
			buildCommand,
			startCommand,
			baseDirectory,
			publishDirectory
		});
		try {
			saveBuildLog({ line: 'Deployment started.', buildId, applicationId });
			const { stderr } = await asyncExecShell(
				`DOCKER_HOST=${host} docker run --env-file=${workdir}/.env ${labels.join(
					' '
				)} --name ${imageId} --network ${
					docker.network
				} --restart always -d ${applicationId}:${tag}`
			);
			if (stderr) console.log(stderr);
			saveBuildLog({ line: 'Deployment successful!', buildId, applicationId });
		} catch (error) {
			saveBuildLog({ line: error, buildId, applicationId });
			sentry.captureException(error);
			throw new Error(error);
		}
		try {
			if (destinationDockerId && destinationDocker.isCoolifyProxyUsed) {
				saveBuildLog({ line: 'Proxy configuration started!', buildId, applicationId });
				await configureProxyForApplication({ domain, imageId, applicationId, port });
				if (isHttps) await letsEncrypt({ domain, id: applicationId });
				await setWwwRedirection(fqdn);
				await reloadHaproxy(destinationDocker.engine);
				saveBuildLog({ line: 'Proxy configuration successful!', buildId, applicationId });
			} else {
				saveBuildLog({
					line: 'Coolify Proxy is not configured for this destination. Nothing else to do.',
					buildId,
					applicationId
				});
			}
		} catch (error) {
			saveBuildLog({ line: error.stdout || error, buildId, applicationId });
			sentry.captureException(error);
		}
	}
}
