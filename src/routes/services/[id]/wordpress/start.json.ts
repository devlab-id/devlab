import { asyncExecShell, createDirectories, getEngine, getUserDetails } from '$lib/common';
import * as db from '$lib/database';
import { promises as fs } from 'fs';
import yaml from 'js-yaml';
import type { RequestHandler } from '@sveltejs/kit';
import { ErrorHandler } from '$lib/database';
import { makeLabelForServices } from '$lib/buildPacks/common';

export const post: RequestHandler = async (event) => {
	const { teamId, status, body } = await getUserDetails(event);
	if (status === 401) return { status, body };

	const { id } = event.params;

	try {
		const service = await db.getService({ id, teamId });
		const {
			type,
			version,
			fqdn,
			destinationDockerId,
			destinationDocker,
			wordpress: {
				mysqlDatabase,
				mysqlUser,
				mysqlPassword,
				extraConfig,
				mysqlRootUser,
				mysqlRootUserPassword
			}
		} = service;

		const network = destinationDockerId && destinationDocker.network;
		const host = getEngine(destinationDocker.engine);

		const { workdir } = await createDirectories({ repository: type, buildId: id });
		const config = {
			wordpress: {
				image: `wordpress:${version}`,
				volume: `${id}-wordpress-data:/var/www/html`,
				environmentVariables: {
					WORDPRESS_DB_HOST: `${id}-mysql`,
					WORDPRESS_DB_USER: mysqlUser,
					WORDPRESS_DB_PASSWORD: mysqlPassword,
					WORDPRESS_DB_NAME: mysqlDatabase,
					WORDPRESS_CONFIG_EXTRA: extraConfig
				}
			},
			mysql: {
				image: `bitnami/mysql:5.7`,
				volume: `${id}-mysql-data:/bitnami/mysql/data`,
				environmentVariables: {
					MYSQL_ROOT_PASSWORD: mysqlRootUserPassword,
					MYSQL_ROOT_USER: mysqlRootUser,
					MYSQL_USER: mysqlUser,
					MYSQL_PASSWORD: mysqlPassword,
					MYSQL_DATABASE: mysqlDatabase
				}
			}
		};
		const composeFile = {
			version: '3.8',
			services: {
				[id]: {
					container_name: id,
					image: config.wordpress.image,
					environment: config.wordpress.environmentVariables,
					networks: [network],
					restart: 'always',
					depends_on: [`${id}-mysql`],
					labels: makeLabelForServices('wordpress')
				},
				[`${id}-mysql`]: {
					container_name: `${id}-mysql`,
					image: config.mysql.image,
					environment: config.mysql.environmentVariables,
					networks: [network],
					restart: 'always'
				}
			},
			networks: {
				[network]: {
					external: true
				}
			},
			volumes: {
				[config.mysql.volume.split(':')[0]]: {
					external: true
				},
				[config.wordpress.volume.split(':')[0]]: {
					external: true
				}
			}
		};
		const composeFileDestination = `${workdir}/docker-compose.yaml`;
		await fs.writeFile(composeFileDestination, yaml.dump(composeFile));

		try {
			await asyncExecShell(
				`DOCKER_HOST=${host} docker volume create ${config.mysql.volume.split(':')[0]}`
			);
			await asyncExecShell(
				`DOCKER_HOST=${host} docker volume create ${config.wordpress.volume.split(':')[0]}`
			);
		} catch (error) {
			console.log(error);
		}

		try {
			await asyncExecShell(`DOCKER_HOST=${host} docker compose -f ${composeFileDestination} up -d`);
			return {
				status: 200
			};
		} catch (error) {
			console.log(error);
			return ErrorHandler(error);
		}
	} catch (error) {
		return ErrorHandler(error);
	}
};
