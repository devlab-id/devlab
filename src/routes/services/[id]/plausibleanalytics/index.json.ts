import { getUserDetails } from '$lib/common';
import * as db from '$lib/database';
import { PrismaErrorHandler } from '$lib/database';
import type { RequestHandler } from '@sveltejs/kit';

export const post: RequestHandler = async (event) => {
	const { status, body } = await getUserDetails(event);
	if (status === 401) return { status, body };

	const { id } = event.params;
	let {
		name,
		fqdn,
		plausibleAnalytics: { email, username }
	} = await event.request.json();

	if (fqdn) fqdn = fqdn.toLowerCase();
	if (email) email = email.toLowerCase();

	try {
		await db.updatePlausibleAnalyticsService({ id, fqdn, name, email, username });
		return { status: 201 };
	} catch (error) {
		return PrismaErrorHandler(error);
	}
};
