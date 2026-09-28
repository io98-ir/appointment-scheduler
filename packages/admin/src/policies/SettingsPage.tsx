import { Policies } from './Policies';

/**
 * Global settings. So far only the default cancellation and reschedule
 * policy (service_id 0), which a service's own policy overrides
 * (implementation-notes §4.12). Custom fields and price rules join it in a
 * later task.
 */
export function SettingsPage() {
	return <Policies serviceId={ 0 } />;
}
