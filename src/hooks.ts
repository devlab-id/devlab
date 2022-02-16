import dotEnvExtended from 'dotenv-extended';
dotEnvExtended.load();
import type { GetSession } from '@sveltejs/kit';
import { handleSession } from 'svelte-kit-cookie-session';
import { getUserDetails, isTeamIdTokenAvailable, sentry } from '$lib/common';
import { version } from '$lib/common';
import cookie from 'cookie';
import { dev } from '$app/env';

export const handle = handleSession(
	{
		secret: process.env['COOLIFY_SECRET_KEY'],
		expires: 30,
		cookie: { secure: false }
	},
	async function ({ event, resolve }) {
		let response;
		try {
			const cookies: Cookies = cookie.parse(event.request.headers.get('cookie') || '');
			if (cookies['kit.session']) {
				const { permission, teamId } = await getUserDetails(event, false);
				event.locals.user = {
					teamId,
					permission,
					isAdmin: permission === 'admin' || permission === 'owner'
				};
			}
			if (cookies.gitlabToken) {
				event.locals.gitlabToken = cookies.gitlabToken;
			}
			response = await resolve(event, {
				ssr: !event.url.pathname.startsWith('/webhooks/success')
			});
		} catch (error) {
			response = await resolve(event, {
				ssr: !event.url.pathname.startsWith('/webhooks/success')
			});
			response.headers.append(
				'Set-Cookie',
				cookie.serialize('kit.session', '', {
					path: '/',
					expires: new Date('Thu, 01 Jan 1970 00:00:01 GMT')
				})
			);
			response.headers.append(
				'Set-Cookie',
				cookie.serialize('teamId', '', {
					path: '/',
					expires: new Date('Thu, 01 Jan 1970 00:00:01 GMT')
				})
			);
			response.headers.append(
				'Set-Cookie',
				cookie.serialize('gitlabToken', '', {
					path: '/',
					expires: new Date('Thu, 01 Jan 1970 00:00:01 GMT')
				})
			);
		} finally {
			return response;
		}
	}
);

export const getSession: GetSession = function (request) {
	return {
		version,
		gitlabToken: request.locals?.gitlabToken || null,
		uid: request.locals.session.data?.uid || null,
		teamId: request.locals.user?.teamId || null,
		permission: request.locals.user?.permission,
		isAdmin: request.locals.user?.isAdmin || false
	};
};

export async function handleError({ error, event }) {
	if (!dev) sentry.captureException(error, { event });
}
