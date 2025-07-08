// =======================================================
// MSC Medical Distribution Platform - MVP DB Schema
// Compliance-first, FHIR-ready, Multi-tenant
// =======================================================

Table tenants {
  id char(36) [pk]
  name varchar(255)
  type varchar(50) // distributor, manufacturer, platform
  settings json
  is_active boolean
  created_at timestamp
  updated_at timestamp
}

Table users {
  id char(36) [pk]
  email varchar(255) [unique, not null]
  password_hash varchar(255)
  first_name varchar(100)
  last_name varchar(100)
  phone varchar(50)
  provider_fhir_id varchar(255)
  user_type varchar(50) // provider, office_manager, sales_rep, admin, manufacturer_rep
  status varchar(50)
  settings json
  created_at timestamp
  updated_at timestamp
}

Table roles {
  id char(36) [pk]
  name varchar(50) [unique, not null]
  description varchar(255)
  created_at timestamp
}

Table permissions {
  id char(36) [pk]
  name varchar(100) [unique, not null]
  description varchar(255)
  created_at timestamp
}

Table user_roles {
  id char(36) [pk]
  user_id char(36) [ref: > users.id]
  role_id char(36) [ref: > roles.id]
  scope_type varchar(50) // organization, facility, manufacturer, etc.
  scope_id char(36)
  created_at timestamp
  deleted_at timestamp
}

Table role_permissions {
  id char(36) [pk]
  role_id char(36) [ref: > roles.id]
  permission_id char(36) [ref: > permissions.id]
  created_at timestamp
}

Table user_facility_assignments {
  id char(36) [pk]
  user_id char(36) [ref: > users.id]
  facility_id char(36) [ref: > organizations.id]
  role varchar(50)
  can_order boolean
  can_view_orders boolean
  can_view_financial boolean
  can_manage_verifications boolean
  can_order_for_providers json
  is_primary_facility boolean
  assigned_at timestamp
}

Table organizations {
  id char(36) [pk]
  tenant_id char(36) [ref: > tenants.id]
  parent_id char(36) [ref: > organizations.id]
  organization_fhir_id varchar(255)
  type varchar(50) // facility, manufacturer, distributor, payer, provider_practice
  name varchar(255)
  npi varchar(20)
  tax_id varchar(20)
  business_email varchar(255)
  business_phone varchar(50)
  settings json
  status varchar(50)
  activated_at timestamp
  created_at timestamp
  updated_at timestamp
}

Table patient_references {
  id char(36) [pk]
  patient_fhir_id varchar(255) [unique, not null]
  patient_display_id varchar(10)
  display_metadata json
  tenant_id char(36) [ref: > tenants.id]
  created_at timestamp
}

Table episodes {
  id char(36) [pk]
  tenant_id char(36) [ref: > tenants.id]
  episode_number varchar(50)
  patient_fhir_id varchar(255)
  primary_provider_fhir_id varchar(255)
  primary_facility_id char(36) [ref: > organizations.id]
  type varchar(50)
  sub_type varchar(100)
  status varchar(50)
  diagnosis_fhir_refs json
  procedure_fhir_refs json
  estimated_duration_days int
  priority varchar(50)
  start_date date
  target_date date
  end_date date
  tags json
  created_at timestamp
  updated_at timestamp
  created_by char(36) [ref: > users.id]
}

Table episode_care_team {
  id char(36) [pk]
  episode_id char(36) [ref: > episodes.id]
  user_id char(36) [ref: > users.id]
  provider_fhir_id varchar(255)
  role varchar(50)
  can_order boolean
  can_modify boolean
  can_view_financial boolean
  assigned_date date
  removed_date date
}

Table product_requests {
  id char(36) [pk]
  episode_id char(36) [ref: > episodes.id]
  request_number varchar(50)
  requested_by char(36) [ref: > users.id]
  requested_for_provider_fhir_id varchar(255)
  request_type varchar(50)
  status varchar(50)
  clinical_need text
  urgency varchar(50)
  product_categories json
  specific_products json
  needed_by_date date
  submitted_at timestamp
  reviewed_at timestamp
  converted_to_order_id char(36) [ref: > orders.id]
  created_at timestamp
  updated_at timestamp
}

Table orders {
  id char(36) [pk]
  episode_id char(36) [ref: > episodes.id]
  product_request_id char(36) [ref: > product_requests.id]
  order_number varchar(50)
  order_type varchar(50)
  status varchar(50)
  ordering_provider_fhir_id varchar(255)
  ordered_by_user_id char(36) [ref: > users.id]
  facility_id char(36) [ref: > organizations.id]
  manufacturer_id char(36) [ref: > organizations.id]
  service_date date
  ship_to_type varchar(50)
  requires_insurance_verification boolean
  requires_prior_auth boolean
  verification_status varchar(50)
  estimated_total decimal
  final_total decimal
  patient_responsibility decimal
  insurance_coverage decimal
  compliance_check_status varchar(50)
  submitted_at timestamp
  approved_at timestamp
  transmitted_at timestamp
  shipped_at timestamp
  delivered_at timestamp
  internal_notes text
  manufacturer_notes text
  created_at timestamp
  updated_at timestamp
}

Table order_items {
  id char(36) [pk]
  order_id char(36) [ref: > orders.id]
  product_id char(36) [ref: > products.id]
  quantity int
  unit_of_measure varchar(20)
  unit_price decimal
  discount_percentage decimal
  line_total decimal
  specific_indication text
  created_at timestamp
}

Table verifications {
  id char(36) [pk]
  episode_id char(36) [ref: > episodes.id]
  order_id char(36) [ref: > orders.id]
  verification_type varchar(50)
  verification_subtype varchar(100)
  required_by_organization_id char(36) [ref: > organizations.id]
  payer_organization_id char(36) [ref: > organizations.id]
  form_template_id varchar(255)
  form_provider varchar(50)
  status varchar(50)
  required_fields json
  completed_fields json
  completeness_percentage decimal
  determination varchar(50)
  coverage_details json
  external_submission_id varchar(255)
  external_status varchar(100)
  verified_date date
  expires_date date
  submitted_document_ids json
  created_at timestamp
  updated_at timestamp
  completed_at timestamp
}

Table products {
  id char(36) [pk]
  tenant_id char(36) [ref: > tenants.id]
  manufacturer_id char(36) [ref: > organizations.id]
  sku varchar(100)
  manufacturer_part_number varchar(100)
  category varchar(50)
  sub_category varchar(100)
  name varchar(255)
  description text
  hcpcs_code varchar(20)
  cpt_codes json
  requires_prescription boolean
  requires_verification boolean
  requires_sizing boolean
  specifications json
  is_active boolean
  created_at timestamp
  updated_at timestamp
}

Table compliance_rules {
  id char(36) [pk]
  tenant_id char(36) [ref: > tenants.id]
  rule_name varchar(255)
  rule_type varchar(50)
  applies_to_categories json
  applies_to_products json
  applies_to_states json
  applies_to_payers json
  rule_engine varchar(50)
  rule_definition text
  required_documentation json
  required_fields json
  severity varchar(50)
  can_override boolean
  effective_date date
  expiration_date date
  is_active boolean
  created_at timestamp
}

Table order_compliance_checks {
  id char(36) [pk]
  order_id char(36) [ref: > orders.id]
  check_type varchar(50)
  passed boolean
  applied_rules json
  failures json
  warnings json
  overridden boolean
  override_reason text
  overridden_by char(36) [ref: > users.id]
  checked_at timestamp
}

Table documents {
  id char(36) [pk]
  entity_type varchar(50)
  entity_id char(36)
  document_type varchar(50)
  document_name varchar(255)
  storage_path varchar(500)
  mime_type varchar(100)
  file_size_bytes bigint
  requires_signature boolean
  signature_type varchar(50)
  signed_at timestamp
  signature_method varchar(50)
  metadata json
  uploaded_by char(36) [ref: > users.id]
  uploaded_at timestamp
  retention_until date
}

Table commission_rules {
  id char(36) [pk]
  tenant_id char(36) [ref: > tenants.id]
  rule_name varchar(255)
  applies_to_products json
  applies_to_categories json
  applies_to_facilities json
  commission_type varchar(50)
  base_rate decimal
  tier_definitions json
  split_rules json
  effective_date date
  end_date date
  created_at timestamp
}

Table commission_records {
  id char(36) [pk]
  order_id char(36) [ref: > orders.id]
  user_id char(36) [ref: > users.id]
  rule_id char(36) [ref: > commission_rules.id]
  base_amount decimal
  commission_amount decimal
  status varchar(50)
  payment_period varchar(20)
  paid_date date
  payment_reference varchar(100)
  created_at timestamp
  approved_at timestamp
}

Table integration_events {
  id char(36) [pk]
  entity_type varchar(50)
  entity_id char(36)
  integration_type varchar(50)
  event_type varchar(100)
  request_data json
  response_data json
  status varchar(50)
  error_message text
  duration_ms int
  created_at timestamp
}

Table audit_logs {
  id char(36) [pk]
  user_id char(36) [ref: > users.id]
  acting_as varchar(255)
  action varchar(100)
  entity_type varchar(50)
  entity_id char(36)
  changes json
  ip_address varchar(45)
  user_agent text
  created_at timestamp
}
