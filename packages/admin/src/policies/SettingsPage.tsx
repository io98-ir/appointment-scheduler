import { Coupons } from '../coupons/Coupons';
import { Fields } from '../fields/Fields';
import { Policies } from './Policies';

/**
 * Global settings: the default cancellation and reschedule policy
 * (service_id 0), which a service's own policy overrides
 * (implementation-notes §4.12), the global custom fields every service
 * shows, and the coupons. Time rules join it in a later part of T3.5.
 */
export function SettingsPage() {
	return (
		<>
			<Policies serviceId={ 0 } />
			<Fields scope="global" />
			<Coupons />
		</>
	);
}
