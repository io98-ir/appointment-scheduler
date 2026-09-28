import { ApiError, type ApiClient } from '@vaqtyar/shared';

export interface MoveRequest {
	id: number;
	/** ISO 8601 with the location's offset. */
	start: string;
	staff: number;
	/** Past the service's policy; needs override_policies and a reason. */
	override?: boolean;
	reason?: string;
}

export type MoveResult = { moved: true } | { moved: false; refusal: string };

/**
 * POST /appointments/{id}/reschedule. The policy's refusal (a "policy.*"
 * code) comes back as a result, as staff may override it; any other error
 * is thrown, for the app's snackbar.
 *
 * @param api
 * @param request
 */
export async function reschedule(
	api: ApiClient,
	request: MoveRequest
): Promise< MoveResult > {
	const { id, ...body } = request;
	try {
		await api.post( `/appointments/${ id }/reschedule`, body );
	} catch ( error ) {
		if ( error instanceof ApiError && error.code.startsWith( 'policy.' ) ) {
			return { moved: false, refusal: error.message };
		}
		throw error;
	}

	return { moved: true };
}
