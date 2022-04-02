import { toast } from '@zerodevx/svelte-toast';
import { errorNotification } from '$lib/form';
import { post } from '$lib/api';

type Props = {
	isNew: boolean;
	name: string;
	value: string;
	isBuildSecret?: boolean;
	isPRMRSecret?: boolean;
	isNewSecret?: boolean;
	applicationId: string;
	dispatch: (name: string) => void;
};

export async function saveSecret({
	isNew,
	name,
	value,
	isBuildSecret,
	isPRMRSecret,
	isNewSecret,
	applicationId,
	dispatch
}: Props): Promise<void> {
	if (!name) return errorNotification('Name is required.');
	if (!value) return errorNotification('Value is required.');
	try {
		await post(`/applications/${applicationId}/secrets.json`, {
			name,
			value,
			isBuildSecret,
			isPRMRSecret,
			isNew: isNew || false
		});
		dispatch('refresh');
		if (isNewSecret) {
			name = '';
			value = '';
			isBuildSecret = false;
		}
		toast.push('Secret saved.');
	} catch ({ error }) {
		return errorNotification(error);
	}
}
