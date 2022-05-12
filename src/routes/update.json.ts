import { dev } from '$app/env';
import { asyncExecShell, version } from '$lib/common';
import { asyncSleep } from '$lib/components/common';
import { ErrorHandler } from '$lib/database';
import * as db from '$lib/database';
import type { RequestHandler } from '@sveltejs/kit';
import compare from 'compare-versions';
import got from 'got';

export const get: RequestHandler = async (request) => {
	try {
		const currentVersion = version;
		const versions = await got
			.get(
				`https://get.coollabs.io/versions.json?appId=${process.env['COOLIFY_APP_ID']}&version=${currentVersion}`
			)
			.json();
		const latestVersion =
			request.url.hostname === 'staging.coolify.io'
				? versions['coolify'].next.version
				: versions['coolify'].main.version;
		const isUpdateAvailable = compare(latestVersion, currentVersion);
		return {
			body: {
				isUpdateAvailable: isUpdateAvailable === 1,
				latestVersion
			}
		};
	} catch (error) {
		console.log(error);
		return ErrorHandler(error);
	}
};

export const post: RequestHandler = async (event) => {
	const { type, latestVersion } = await event.request.json();
	if (type === 'update') {
		try {
			if (!dev) {
				const { isAutoUpdateEnabled } = await db.prisma.setting.findFirst();
				await asyncExecShell(`docker pull coollabsio/coolify:${latestVersion}`);
				await asyncExecShell(`env | grep COOLIFY > .env`);
				await asyncExecShell(
					`sed -i '/COOLIFY_AUTO_UPDATE=/c\COOLIFY_AUTO_UPDATE=${isAutoUpdateEnabled}' .env`
				);
				await asyncExecShell(
					`docker run --rm -tid --env-file .env -v /var/run/docker.sock:/var/run/docker.sock -v coolify-db coollabsio/coolify:${latestVersion} /bin/sh -c "env | grep COOLIFY > .env && echo 'TAG=${latestVersion}' >> .env && docker stop -t 0 coolify coolify-redis && docker rm coolify coolify-redis && docker compose up -d --force-recreate"`
				);
				return {
					status: 200,
					body: {}
				};
			} else {
				console.log(latestVersion);
				await asyncSleep(2000);
				return {
					status: 200,
					body: {}
				};
			}
		} catch (error) {
			return ErrorHandler(error);
		}
	} else if (type === 'migrateToTraefik') {
		try {
			const settings = await db.prisma.setting.findFirst({});
			await db.prisma.setting.update({
				where: { id: settings.id },
				data: { disableHaproxy: true }
			});
			await asyncExecShell(`docker stop -t 0 coolify-haproxy`);
			await asyncExecShell(`docker rm coolify-haproxy`);
			return {
				status: 200,
				body: {}
			};
		} catch (error) {
			return ErrorHandler(error);
		}
	}
	return {
		status: 500
	};
};
