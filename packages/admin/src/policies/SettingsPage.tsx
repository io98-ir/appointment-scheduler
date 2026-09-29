import { __ } from '@wordpress/i18n';

import { Coupons } from '../coupons/Coupons';
import { Fields } from '../fields/Fields';
import { BrandForm } from '../setup/BrandForm';
import { PaymentSettingsForm } from '../setup/PaymentSettingsForm';
import { SmsSettingsForm } from '../setup/SmsSettingsForm';
import { TimeRules } from '../timeRules/TimeRules';
import { Policies } from './Policies';

/**
 * Global settings: the default cancellation and reschedule policy
 * (service_id 0), which a service's own policy overrides
 * (implementation-notes §4.12), the global custom fields every service
 * shows, the coupons, the time-based price rules, and the owner's look,
 * SMS provider and online payment (T6.1).
 */
export function SettingsPage() {
	return (
		<>
			<section className="vqy-admin__panel">
				<h2>{ __( 'Brand', 'vaqtyar' ) }</h2>
				<BrandForm />
			</section>
			<section className="vqy-admin__panel">
				<h2>{ __( 'SMS', 'vaqtyar' ) }</h2>
				<SmsSettingsForm />
			</section>
			<section className="vqy-admin__panel">
				<h2>{ __( 'Online payment', 'vaqtyar' ) }</h2>
				<PaymentSettingsForm />
			</section>
			<Policies serviceId={ 0 } />
			<Fields scope="global" />
			<Coupons />
			<TimeRules />
		</>
	);
}
