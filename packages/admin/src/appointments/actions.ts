import {
	ApiError,
	type ApiClient,
	type AppointmentStatus,
} from '@vaqtyar/shared';

/** POST /appointments/{id}/{action} (docs/api.md). */
export type Action = 'approve' | 'complete' | 'no-show' | 'cancel';

export type ActResult = { done: true } | { done: false; refusal: string };

/**
 * The changes an appointment's status allows, in the order they are shown
 * (booking-engine §4). The server checks again; e.g. complete and no-show
 * wait for the start.
 *
 * @param status
 */
export function actionsFor( status: AppointmentStatus ): Action[] {
	switch ( status ) {
		case 'pending_approval':
			return [ 'approve', 'cancel' ];
		case 'pending_payment':
			return [ 'cancel' ];
		case 'confirmed':
			return [ 'complete', 'no-show', 'cancel' ];
		default:
			return [];
	}
}

/**
 * Makes the change. The policy's refusal (a "policy.*" code) comes back as
 * a result, as staff may override it with a reason; any other error is
 * thrown, for the app's snackbar.
 *
 * @param api
 * @param id
 * @param action
 * @param body          sent with the request.
 * @param body.reason   why, for a cancel.
 * @param body.override whether staff override the policy.
 */
export async function act(
	api: ApiClient,
	id: number,
	action: Action,
	body: { reason?: string; override?: boolean } = {}
): Promise< ActResult > {
	try {
		await api.post( `/appointments/${ id }/${ action }`, body );
	} catch ( error ) {
		if ( error instanceof ApiError && error.code.startsWith( 'policy.' ) ) {
			return { done: false, refusal: error.message };
		}
		throw error;
	}

	return { done: true };
}
