Here’s a complete implementation guide for introducing a friendly_patient_tag system into the MSC-MVP Wound Care Platform, using the first two letters of the first and last name for non-PHI references.

MSC-MVP Platform Implementation Guide: friendly_patient_tag Identifier
Version: 1.0
Author: Ricky Valentine
Last Updated: May 2025
Scope: azure mysql Non-PHI System
Purpose: Create a non-PHI, human-readable patient reference tag to be used in admin workflows, dashboards, logs, and document metadata.

1. Overview
The friendly_patient_tag provides a lightweight, non-unique identifier in the format:
<First 2 letters of First Name><First 2 letters of Last Name> → uppercased
Example: John Smith → JOSM
This tag:
Does not contain full PHI


Is safe to store in Supabase


Enhances UX for order tracking, logging, and audit purposes


Is generated during order intake (or when patient data is first linked)



2. Security & Compliance Design
HIPAA Compliance Notes
This tag alone is not PHI if:


Not combined with DOB, address, full name, etc.


Stored only in non-PHI context (e.g., orders table)


It must not be exposed in patient-facing views


Use only internally (Admin UI, audit logs, document metadata)


Where Not to Use
Patient-facing portals


PDFs shown to or signed by patients


Anywhere full PHI is present



3. Database Migration (Supabase)
Add a new column to the orders table:
ALTER TABLE orders
ADD COLUMN friendly_patient_tag VARCHAR(10);
You can also index it for filtering/search:
CREATE INDEX idx_orders_friendly_tag ON orders (friendly_patient_tag);

4. Laravel Backend Service Implementation
Helper Function
// app/Helpers/FriendlyTagHelper.php

namespace App\Helpers;

class FriendlyTagHelper
{
    public static function generate(string $firstName, string $lastName): string
    {
        $tag = strtoupper(
            substr(preg_replace('/[^a-zA-Z]/', '', $firstName), 0, 2) .
            substr(preg_replace('/[^a-zA-Z]/', '', $lastName), 0, 2)
        );
        return $tag ?: 'UNKN'; // Fallback in case of missing data
    }
}

Order Creation Logic
When creating an order:
use App\Helpers\FriendlyTagHelper;

$patient = $this->fetchPatientDemographicsFromFHIR($patientFhirId); // via Azure

$tag = FriendlyTagHelper::generate($patient['first_name'], $patient['last_name']);

$order = Order::create([
    // other fields
    'patient_fhir_id' => $patientFhirId,
    'friendly_patient_tag' => $tag,
]);

5. Admin UI Integration (React)
Table Display Component
<TableRow>
  <TableCell>{order.friendly_patient_tag}</TableCell>
  <TableCell>{order.order_status}</TableCell>
  <TableCell>{order.facility_name}</TableCell>
  <TableCell>${order.total_order_value.toFixed(2)}</TableCell>
</TableRow>
Search Field (Optional)
Add a filterable input to search by friendly_patient_tag:
<Input
  label="Patient Tag"
  value={filters.friendlyTag}
  onChange={(e) => setFilters({ ...filters, friendlyTag: e.target.value.toUpperCase() })}
/>

6. Usage in DocuSeal Metadata
When generating a document:
'metadata' => [
    'order_id' => $order->id,
    'friendly_patient_tag' => $order->friendly_patient_tag,
    'generated_by' => auth()->id(),
]
This is useful for:
Non-PHI audit trails


Matching documents with admin queues


Troubleshooting document workflows



7. Audit Logging Integration
Use the tag in activity logs:
activity()
    ->performedOn($order)
    ->withProperties([
        'friendly_patient_tag' => $order->friendly_patient_tag,
        'order_id' => $order->id,
    ])
    ->log('order_created');

8. Optional Enhancements
Tag + Suffix Format
To improve uniqueness without exposing PHI:
$shortId = substr($order->id, 0, 4); // or a hash
$tag = FriendlyTagHelper::generate($firstName, $lastName) . '-' . strtoupper($shortId);
Central Service Class
Encapsulate tag logic into a reusable service:
class PatientReferenceService {
    public function generateTagFromFHIRId(string $patientFhirId): string {
        // fetch from Azure and run tag generation
    }
}

9. Testing
Unit Test: FriendlyTagHelperTest.php
public function testGeneratesCorrectTag() {
    $tag = FriendlyTagHelper::generate("John", "Smith");
    $this->assertEquals("JOSM", $tag);
}

public function testHandlesShortNames() {
    $tag = FriendlyTagHelper::generate("Al", "Li");
    $this->assertEquals("ALLI", $tag);
}

10. Deployment Steps
Apply DB migration to add column.


Add helper and test coverage.


Update order creation to generate tag.


Update UI table views and filters.


Integrate tag into DocuSeal and audit metadata.


Deploy in a monitored release with access control.



11. Summary
Feature
Purpose
friendly_patient_tag
Enables non-PHI patient referencing
Based on
First 2 letters of first and last name
Stored in
mysql orders table
Exposed in
Admin UI, audit logs, document metadata
Not exposed in
Patient-facing UIs or PHI-rich contexts
HIPAA compliance
Safe under Safe Harbor if used as standalone ID



