/**
 * Service type entity - represents different service categories
 * Examples: GYOGYTORNA (physiotherapy), PT (personal training), MASSZAZS (massage)
 */
export interface ServiceType {
  id: number;
  code: string;
  name: string;
  description: string | null;
  default_entry_fee_brutto: number;
  default_trainer_fee_brutto: number;
  is_active: boolean;
  created_at: string;
  updated_at: string;
  deleted_at?: string | null;
}

/**
 * Client price code entity - client-specific pricing per service type
 */
export interface ClientPriceCode {
  id: number;
  client_id: number;
  client_email: string;
  service_type_id: number;
  service_type?: ServiceType;
  price_code: string | null;
  entry_fee_brutto: number;
  trainer_fee_brutto: number;
  currency: string;
  valid_from: string;
  valid_until: string | null;
  is_active: boolean;
  created_by: number | null;
  created_at: string;
  updated_at: string;
}

/**
 * Response from the pricing resolve endpoint
 */
export interface PricingResolveResponse {
  entry_fee_brutto: number;
  trainer_fee_brutto: number;
  currency: string;
  source: 'client_price_code' | 'service_type_default';
  price_code?: string | null;
}

/**
 * Form data for creating a new service type
 */
export interface ServiceTypeFormData {
  code: string;
  name: string;
  description?: string;
  default_entry_fee_brutto: number;
  default_trainer_fee_brutto: number;
  is_active?: boolean;
}

/**
 * Form data for creating/updating a client price code
 */
export interface ClientPriceCodeFormData {
  service_type_id: number;
  price_code?: string;
  entry_fee_brutto: number;
  trainer_fee_brutto: number;
  currency?: string;
  valid_from: string;
  valid_until?: string | null;
  is_active?: boolean;
}
