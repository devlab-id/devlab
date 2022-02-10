import { dev } from '$app/env';
import { getDomain, getUserDetails } from '$lib/common';
import * as db from '$lib/database';
import { listSettings, PrismaErrorHandler } from '$lib/database';
import {
	checkContainer,
	configureCoolifyProxyOff,
	configureCoolifyProxyOn,
	forceSSLOffApplication,
	forceSSLOnApplication,
	reloadHaproxy,
	startCoolifyProxy
} from '$lib/haproxy';
import { letsEncrypt } from '$lib/letsencrypt';
import type { RequestHandler } from '@sveltejs/kit';

export const get: RequestHandler = async (event) => {
	const { status, body } = await getUserDetails(event);
	if (status === 401) return { status, body };

	try {
		const settings = await listSettings();
		return {
			status: 200,
			body: {
				settings
			}
		};
	} catch (error) {
		return PrismaErrorHandler(error);
	}
};

export const del: RequestHandler = async (event) => {
	const { teamId, status, body } = await getUserDetails(event);
	if (teamId !== '0')
		return {
			status: 401,
			body: {
				message: 'You do not have permission to do this. \nAsk an admin to modify your permissions.'
			}
		};
	if (status === 401) return { status, body };

	const { fqdn } = await event.request.json();

	try {
		await db.prisma.setting.update({ where: { fqdn }, data: { fqdn: null } });
		const domain = getDomain(fqdn);
		await configureCoolifyProxyOff({ domain });
		return {
			status: 201
		};
	} catch (error) {
		return PrismaErrorHandler(error);
	}
};
export const post: RequestHandler = async (event) => {
	const { teamId, status, body } = await getUserDetails(event);
	if (teamId !== '0')
		return {
			status: 401,
			body: {
				message: 'You do not have permission to do this. \nAsk an admin to modify your permissions.'
			}
		};
	if (status === 401) return { status, body };

	const { fqdn, isRegistrationEnabled } = await event.request.json();
	try {
		const {
			id,
			fqdn: oldFqdn,
			isRegistrationEnabled: oldIsRegistrationEnabled
		} = await db.listSettings();
		if (oldIsRegistrationEnabled !== isRegistrationEnabled) {
			await db.prisma.setting.update({ where: { id }, data: { isRegistrationEnabled } });
		}
		if (oldFqdn && oldFqdn !== fqdn) {
			const oldDomain = getDomain(oldFqdn);
			if (oldFqdn) {
				await configureCoolifyProxyOff({ domain: oldDomain });
			}
		}
		if (fqdn) {
			const found = await checkContainer('/var/run/docker.sock', 'coolify-haproxy');
			if (!found) await startCoolifyProxy('/var/run/docker.sock');
			const domain = getDomain(fqdn);
			const isHttps = fqdn.startsWith('https://');
			if (domain) {
				await configureCoolifyProxyOn({ domain });
				if (isHttps && !dev) {
					await letsEncrypt({ domain, isCoolify: true });
					await forceSSLOnApplication({ domain });
					await reloadHaproxy('/var/run/docker.sock');
				}
			}

			await db.prisma.setting.update({ where: { id }, data: { fqdn } });
			await db.prisma.destinationDocker.updateMany({
				where: { engine: '/var/run/docker.sock' },
				data: { isCoolifyProxyUsed: true }
			});
		}

		return {
			status: 201
		};
	} catch (error) {
		return PrismaErrorHandler(error);
	}
};
