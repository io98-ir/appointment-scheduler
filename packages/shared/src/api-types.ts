/**
 * Shapes of the REST API, kept by hand next to the PHP schemas (tech-stack
 * §2): a change of a schema in PHP updates its type here in the same commit.
 */

/** Money in the smallest unit of the currency (architecture §9). */
export interface Money {
	amount: number;
	currency: 'IRR';
}

/**
 * Every error response (architecture §8). Errors WordPress raises before the
 * plugin's code runs (unknown route, a parameter failing the schema) have no
 * details or request_id.
 */
export interface ErrorEnvelope {
	code: string;
	message: string;
	data: {
		status: number;
		details?: Record< string, unknown >;
		request_id?: string;
	};
}
