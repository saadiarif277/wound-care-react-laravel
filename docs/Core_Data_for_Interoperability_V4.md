# United States Core Data

# for Interoperability

## Version 4 (March 2025 Errata)

This communication was printed, published, or produced and disseminated at U.S. taxpayer expense.


## The USCDI is a standardized

## set of health data classes and

## constituent data elements for

## nationwide, interoperable

## health information exchange.

```
A USCDI Data Class is an aggregation of Data
Elements by a common theme or use case.
```
```
A USCDI Data Element is a piece of data
defined in USCDI for access, exchange, or use
of electronic health information.
```

### Version History

##### Version # Description of change Version Date

##### 4 Publication July 2023

##### 4 (October 2023

##### Errata)

##### Applicable Vocabulary Standard

##### updates for Laboratory Result

##### Interpretation and Specimen Condition

##### Acceptability. Result Unit of Measure

##### definition clarification. Change log

##### technical corrections.

##### October 2023

##### 4 (March 2025

##### Errata)

##### Consistent with Executive Order 14168

##### the Sex, Sexual Orientation, and Gender

##### Identity elements have been removed or

##### updated in the Patient

##### Demographics/Information data class.

##### March 2025


### USCDI v4 Summary of Data Classes and Data Elements

**Allergies and Intolerances**

- Substance (Medication)
- Substance (Drug Class)
- Substance (Non-Medication)
- Reaction

**Care Team Member(s)**

- Care Team Member Name
- Care Team Member Identifier
- Care Team Member Role
- Care Team Member Location
- Care Team Member Telecom

**Clinical Notes**

- Consultation Note
- Discharge Summary Note
- History & Physical
- Procedure Note
- Progress Note

**Clinical Tests**

- Clinical Test
- Clinical Test Result/Report

**Diagnostic Imaging**

- Diagnostic Imaging Test
- Diagnostic Imaging Report

**Encounter Information**

- Encounter Type
- Encounter Identifier
- Encounter Diagnosis
- Encounter Time
- Encounter Location
- Encounter Disposition

**Facility Information**

- Facility Identifier
- Facility Type
- Facility Name

**Goals and Preferences**

- Patient Goals
- SDOH Goals
- Treatment Intervention Preference
- Care Experience Preference

**Health Insurance Information**

- Coverage Status
- Coverage Type
- Relationship to Subscriber
- Member Identifier
- Subscriber Identifier
- Group Identifier
- Payer Identifier

```
Health Status Assessments
```
- Health Concerns
- Functional Status
- Disability Status
- Mental/Cognitive Status
- Pregnancy Status
- Alcohol Use
- Substance Use
- Physical Activity
- SDOH Assessment
- Smoking Status
**Immunizations**
- Immunizations
**Laboratory**
- Tests
- Values/Results
- Specimen Type
- Result Status
- Result Unit of Measure
- Result Reference Range
- Result Interpretation
- Specimen Source Site
- Specimen Identifier
- Specimen Condition Acceptability
**Medical Devices**
- Unique Device Identifier -
    Implantable
**Medications**
- Medications
- Dose
- Dose Unit of Measure
- Indication
- Fill Status
- Medication Instructions
- Medication Adherence
**Patient Demographics/
Information**
- First Name
- Last Name
- Middle Name
    (Including middle initial)
- Name Suffix
- Previous Name
- Date of Birth
- Date of Death
- Race
- Ethnicity
- Tribal Affiliation

```
Patient Demographics/
Information (cont.)
```
- Sex
- Preferred Language
- Current Address
- Previous Address
- Phone Number
- Phone Number Type
- Email Address
- Related Person’s Name
- Relationship Type
- Occupation
- Occupation Industry
**Patient Summary and Plan**
- Assessment and Plan of Treatment
**Problems**
- Problems
- SDOH Problems/Health Concerns
- Date of Diagnosis
- Date of Resolution
**Procedures**
- Procedures
- Performance Time
- SDOH Interventions
- Reason for Referral
**Provenance**
- Author Time Stamp
- Author Organization
**Vital Signs**
- Systolic Blood Pressure
- Diastolic Blood Pressure
- Average Blood Pressure
- Heart Rate
- Respiratory Rate
- Body Temperature
- Body Height
- Body Weight
- Pulse Oximetry
- Inhaled Oxygen Concentration
- BMI Percentile (2 - 20 years)
- Weight-for-length Percentile
    (Birth - 24 Months)
- Head Occipital-frontal
    Circumference Percentile
    (Birth- 36 Months)


```
DATA CLASS
```
#### ALLERGIES AND INTOLERANCES

Harmful or undesired physiological responses associated with exposure to a substance.

##### Data Element Applicable Vocabulary Standard(s)

```
Standards listed are required. If more than one is
listed, at least one is required unless otherwise
noted. If a cell is empty, an applicable vocabulary
standard has not been identified.
```
##### Substance (Medication)

```
Pharmacologic agent believed to cause a
harmful or undesired physiologic response
following exposure.
```
- RxNorm Full Monthly Release, July 3, 2023

##### Substance (Drug Class)

```
Pharmacologic category for an agent believed
to cause a harmful or undesired physiologic
response following exposure.
```
- Systematized Nomenclature of Medicine
    Clinical Terms (SNOMED CT®) U.S. Edition,
    March 2023 Release

##### Substance (Non-Medication)

```
Non-pharmacologic agent believed to cause a
harmful or undesired physiologic response
following exposure.
```
```
Examples include but are not limited to latex,
```
#### eggs, pollen, and peanuts.

- Systematized Nomenclature of Medicine
    Clinical Terms (SNOMED CT) U.S. Edition,
    March 2023 Release

##### Reaction

```
Harmful or undesired physiologic response
following exposure to a pharmacologic agent or
class of agents.
```
- Systematized Nomenclature of Medicine
    Clinical Terms (SNOMED CT) U.S. Edition,
    March 2023 Release


```
DATA CLASS
```
#### CARE TEAM MEMBER(S)

Information on a person who participates or is expected to participate in the care of a patient.

##### Data Element Applicable Vocabulary Standard(s)

```
Standards listed are required. If more than one is
listed, at least one is required unless otherwise
noted. If a cell is empty, an applicable vocabulary
standard has not been identified.
```
**Care Team Member Name** (^)

##### Care Team Member Identifier

```
Sequence of characters used to uniquely refer
to a member of the care team.
```
```
Examples include but are not limited to National
Provider Identifier (NPI) and National Council of
State Boards of Nursing Identifier (NCSBN ID).
```
##### Care Team Member Role

```
Responsibility of an individual within the care
team.
```
```
Examples include but are not limited to primary
```
#### care physician and caregiver.

##### Care Team Member Location

```
Place where care is delivered by a care team
member.
```
```
Examples include but are not limited to clinic
address and location description.
```
##### Care Team Member Telecom

```
Phone or email contact information for a care
team member.
```
- ITU-T E.123, Series E: Overall Network
    Operation, Telephone Service, Service
    Operation and Human Factors, International
    operation - General provisions concerning users:
    Notation for national and international telephone
    numbers, email addresses and web addresses
    (incorporated by reference in § 170.299); and
- ITU-T E.164, Series E: Overall Network
    Operation, Telephone Service, Service
    Operation and Human Factors, International
    operation - Numbering plan of the international
    telephone service: The international public
    telecommunication numbering plan


```
DATA CLASS
```
#### CLINICAL NOTES

Narrative patient data relevant to the context identified by note types.

##### Data Element Applicable Vocabulary Standard(s)

```
Standards listed are required. If more than one is
listed, at least one is required unless otherwise
noted. If a cell is empty, an applicable vocabulary
standard has not been identified.
```
##### Consultation Note

```
Response to request from a clinician for an
opinion, advice, or service from another
clinician.
```
```
Examples include but are not limited to
dermatology, dentistry, and acupuncture.
```
- Logical Observation Identifiers Names and
    Codes (LOINC®) version 2.
       - At minimum: Consult Note (LOINC code
          11488-4)

##### Discharge Summary Note

```
Synopsis of a patient’s admission and course in
a hospital or post-acute care setting.
```
```
Usage note: Must contain admission and
discharge dates and locations, discharge
instructions, and reason(s) for hospitalization.
```
```
Examples include but are not limited to
dermatology discharge summary, hematology
discharge summary, and neurology discharge
summary.
```
- Logical Observation Identifiers Names and
    Codes (LOINC) version 2.
       - At minimum: Discharge Summary (LOINC
          code 18842-5)

##### History & Physical

```
Summary of current and past conditions and
observations used to inform an episode of care.
```
```
Examples include but are not limited to
admission, surgery, and other procedure.
```
- Logical Observation Identifiers Names and
    Codes (LOINC) version 2.
       - At minimum: History and Physical Note
          (LOINC code 34117-2)

##### Procedure Note

```
Synopsis of non-operative procedure.
```
```
Examples include but are not limited to
interventional cardiology, gastrointestinal
```
#### endoscopy, and osteopathic manipulation.

- Logical Observation Identifiers Names and
    Codes (LOINC) version 2.
       - At minimum: Procedure Note (LOINC code
          28570-0)


##### Data Element Applicable Vocabulary Standard(s)

```
Standards listed are required. If more than one is
listed, at least one is required unless otherwise
noted. If a cell is empty, an applicable vocabulary
standard has not been identified.
```
##### Progress Note

```
Summary of a patient’s interval status during an
encounter.
```
```
Examples include but are not limited to
hospitalization, outpatient visit, and treatment
with a post-acute care provider, or other
healthcare encounter.
```
- Logical Observation Identifiers Names and
    Codes (LOINC) version 2.
       - At minimum: Progress Note (LOINC code
          11506-3)

```
DATA CLASS
```
#### CLINICAL TESTS

Non-imaging and non-laboratory tests performed that result in structured or unstructured findings specific
to the patient to facilitate the diagnosis and management of conditions.

##### Data Element Applicable Vocabulary Standard(s)

```
Standards listed are required. If more than one is
listed, at least one is required unless otherwise
noted. If a cell is empty, an applicable vocabulary
standard has not been identified.
```
##### Clinical Test

```
Non-imaging or non-laboratory test.
```
```
Examples include but are not limited to
electrocardiogram (ECG), visual acuity exam,
macular exam, and graded exercise testing
(GXT).
```
- Logical Observation Identifiers Names and
    Codes (LOINC) version 2.

##### Clinical Test Result/Report

```
Findings of clinical tests.
```

```
DATA CLASS
```
#### DIAGNOSTIC IMAGING

Tests that result in visual images requiring interpretation by a credentialed professional.

##### Data Element Applicable Vocabulary Standard(s)

```
Standards listed are required. If more than one is
listed, at least one is required unless otherwise
noted. If a cell is empty, an applicable vocabulary
standard has not been identified.
```
##### Diagnostic Imaging Test

```
Tests that generate visual images and require
interpretation by qualified professionals.
```
```
Examples include but are not limited to
radiographic, photographic, and video images.
```
- Logical Observation Identifiers Names and
    Codes (LOINC) version 2.

##### Diagnostic Imaging Report

```
Interpreted results of imaging tests.
```
```
Usage Note: Includes both structured and
unstructured (narrative) components.
```
```
DATA CLASS
```
#### ENCOUNTER INFORMATION

Information related to interactions between healthcare providers and a patient.

##### Data Element Applicable Vocabulary Standard(s)

```
Standards listed are required. If more than one is
listed, at least one is required unless otherwise
noted. If a cell is empty, an applicable vocabulary
standard has not been identified.
```
##### Encounter Type

```
Category of health care service.
```
```
Examples include but are not limited to office
visit, telephone assessment, and home visit.
```
##### Encounter Identifier

```
Sequence of characters by which an encounter is
known.
```

##### Data Element Applicable Vocabulary Standard(s)

```
Standards listed are required. If more than one is
listed, at least one is required unless otherwise
noted. If a cell is empty, an applicable vocabulary
standard has not been identified.
```
##### Encounter Diagnosis

```
Coded diagnoses associated with an episode of
care.
```
- Systematized Nomenclature of Medicine Clinical
    Terms (SNOMED CT) U.S. Edition, March 2023
    Release
- International Classification of Diseases, Tenth
    Revision, Clinical Modification (ICD-10-CM)
    2023

##### Encounter Time

```
Date/times related to an encounter.
```
```
Examples include but are not limited to
scheduled appointment time, check in time, and
start and stop times.
```
##### Encounter Location

```
Place where a patient’s care is delivered.
```
##### Encounter Disposition

```
Place or setting to which the patient left a
hospital or encounter.
```
```
DATA CLASS
```
#### FACILITY INFORMATION

Physical place of available services or resources.

##### Data Element Applicable Vocabulary Standard(s)

```
Standards listed are required. If more than one is
listed, at least one is required unless otherwise
noted. If a cell is empty, an applicable vocabulary
standard has not been identified.
```
##### Facility Identifier

```
Sequence of characters representing a physical
place of available services or resources.
```
##### Facility Type

```
Category of service or resource available in a
location.
```
```
Examples include but are not limited to hospital,
laboratory, pharmacy, ambulatory clinic, long-
term and post-acute care facility, and food
pantry.
```

##### Data Element Applicable Vocabulary Standard(s)

```
Standards listed are required. If more than one is
listed, at least one is required unless otherwise
noted. If a cell is empty, an applicable vocabulary
standard has not been identified.
```
##### Facility Name

```
Word or words by which a facility is known.
```
```
DATA CLASS
```
#### GOALS AND PREFERENCES

Desired state to be achieved by a person or a person’s elections to guide care.

##### Data Element Applicable Vocabulary Standard(s)

```
Standards listed are required. If more than one is
listed, at least one is required unless otherwise
noted. If a cell is empty, an applicable vocabulary
standard has not been identified.
```
##### Patient Goals

```
Desired outcomes of patient’s care.
```
##### SDOH Goals

```
Desired future states for an identified Social
Determinants of Health-related health concern,
condition, or diagnosis.
```
```
Examples include but are not limited to food
security, transportation security, and ability to
access health care.
```
- Systematized Nomenclature of Medicine Clinical
    Terms (SNOMED CT) U.S. Edition, March 2023
    Release
- Logical Observation Identifiers Names and
    Codes (LOINC) version 2.

##### Treatment Intervention Preference

```
Person's goals, preferences, and priorities for
care and treatment in case that person is unable
to make medical decisions because of a serious
illness or injury.
```
```
Examples include but are not limited to thoughts
on cardiopulmonary resuscitation, mental health
treatment preferences, and thoughts on pain
management.
```
- Logical Observation Identifiers Names and
    Codes (LOINC) version 2.

##### Care Experience Preference

```
Person's goals, preferences, and priorities for
overall experiences during their care and
treatment.
```
```
Examples include but are not limited to religious
beliefs, dislikes and fears, and thoughts and
feelings to be shared.
```
- Logical Observation Identifiers Names and
    Codes (LOINC) version 2.


```
DATA CLASS
```
#### HEALTH INSURANCE INFORMATION

Data related to an individual’s insurance coverage for health care.

##### Data Element Applicable Vocabulary Standard(s)

```
Standards listed are required. If more than one is
listed, at least one is required unless otherwise
noted. If a cell is empty, an applicable vocabulary
standard has not been identified.
```
##### Coverage Status

```
Presence or absence of health care insurance.
```
##### Coverage Type

```
Category of health care payers, insurance
products, or benefits.
```
```
Examples include but are not limited to Medicaid,
commercial, HMO, Medicare Part D, and dental.
```
##### Relationship to Subscriber

```
Relationship of a patient to the primary insured
person.
```
##### Member Identifier

```
Sequence of characters used to uniquely refer to
an individual with respect to their insurance.
```
##### Subscriber Identifier

```
Sequence of characters used to uniquely refer to
the individual that selects insurance benefits.
```
##### Group Identifier

```
Sequence of characters used to uniquely refer to
a specific health insurance plan.
```
##### Payer Identifier

```
Sequence of characters used to uniquely refer to
an insurance payer.
```

```
DATA CLASS
```
#### HEALTH STATUS ASSESSMENTS

Assessments of a health-related matter of interest, importance, or worry to a patient, patient’s authorized
representative, or patient’s healthcare provider that could identify a need, problem, or condition.

##### Data Element Applicable Vocabulary Standard(s)

```
Standards listed are required. If more than one is
listed, at least one is required unless otherwise
noted. If a cell is empty, an applicable vocabulary
standard has not been identified.
```
##### Health Concerns

```
Health-related issue or worry.
```
```
Examples include but are not limited to weight
gain and cancer risk.
```
##### Functional Status

```
Assessment of a person’s ability to perform
activities of daily living and activities across other
situations and settings.
```
```
Examples include but are not limited to bathing,
ambulation, and preparing a light meal.
```
- Logical Observation Identifiers Names and
    Codes (LOINC) version 2.

##### Disability Status

```
Assessments of a patient’s physical, cognitive,
intellectual, or psychiatric disabilities.
```
```
Examples include but are not limited to- vision,
hearing, memory, and activities of daily living.
```
- Logical Observation Identifiers Names and
    Codes (LOINC) version 2.

##### Mental/Cognitive Status

```
Assessment or screening for the presence of a
mental or behavioral problem.
```
```
Examples include but are not limited to alertness,
orientation, comprehension, concentration, and
immediate memory for simple commands.
```
- Logical Observation Identifiers Names and
    Codes (LOINC) version 2.

##### Pregnancy Status

```
State or condition of being pregnant or intent to
become pregnant.
```
```
Examples include but are not limited to pregnant,
not pregnant, and unknown.
```
##### Alcohol Use

```
Evaluation of a patient's consumption of alcohol.
```
```
Examples include but are not limited to history of
alcohol use, alcohol use disorder identification
test, and alcohol intake assessment.
```
- Logical Observation Identifiers Names and
    Codes (LOINC) version 2.


##### Data Element Applicable Vocabulary Standard(s)

```
Standards listed are required. If more than one is
listed, at least one is required unless otherwise
noted. If a cell is empty, an applicable vocabulary
standard has not been identified.
```
##### Substance Use

```
Evaluation of a patient's reported use of drugs or
other substances for non-medical purposes or in
excess of a valid prescription.
```
```
Examples include but are not limited to
substance use disorder score, and substance
use knowledge assessment.
```
- Logical Observation Identifiers Names and
    Codes (LOINC) version 2.

##### Physical Activity

```
Evaluation of a patient's current or usual
exercise.
```
```
Examples include but are not limited to frequency
of muscle-strengthening physical activity, days
per week with moderate to strenuous physical
activity, and minutes per day of moderate to
strenuous physical activity.
```
- Logical Observation Identifiers Names and
    Codes (LOINC) version 2.

##### SDOH Assessment

```
Screening questionnaire-based, structured
evaluation for a Social Determinants of Health-
related risk.
```
```
Examples include but are not limited to food,
housing, and transportation security.
```
- Logical Observation Identifiers Names and
    Codes (LOINC) version 2.
- Systematized Nomenclature of Medicine Clinical
    Terms (SNOMED CT) U.S. Edition, March 2023
    Release

##### Smoking Status

```
Assessment of a patient’s smoking behaviors.
```
```
Examples include but are not limited to pack-
years and current use.
```
- Systematized Nomenclature of Medicine Clinical
    Terms (SNOMED CT) U.S. Edition, March 2023
    Release


```
DATA CLASS
```
#### IMMUNIZATIONS

Record of vaccine administration.

##### Data Element Applicable Vocabulary Standard(s)

```
Standards listed are required. If more than one is
listed, at least one is required unless otherwise
noted. If a cell is empty, an applicable vocabulary
standard has not been identified.
```
##### Immunizations

```
Vaccine product administered, planned, or
reported.
```
```
Both standards are required:
```
- IIS: Current HL7 Standard Code Set, CVX –
    Vaccines Administered, updates through June 6,
    2023
- Vaccine National Drug Code (NDC) Directory –
    Vaccine NDC Linker Table, updates through
    June 7, 2023

```
DATA CLASS
```
#### LABORATORY

Analysis of clinical specimens to obtain information about the health of a patient.

##### Data Element Applicable Vocabulary Standard(s)

```
Standards listed are required. If more than one is
listed, at least one is required unless otherwise
noted. If a cell is empty, an applicable vocabulary
standard has not been identified.
```
##### Tests

```
Analysis of specimens derived from humans
which provide information for the diagnosis,
prevention, treatment of disease, or assessment
of health.
```
- Logical Observation Identifiers Names and
    Codes (LOINC) version 2.

##### Values/Results

```
Documented findings of a tested specimen
including structured and unstructured
components.
```
- Systematized Nomenclature of Medicine Clinical
    Terms (SNOMED CT) U.S. Edition, March 2023
    Release

##### Specimen Type

```
Substance being sampled or tested.
```
```
Examples include but are not limited to
nasopharyngeal swab, whole blood, serum,
urine, and wound swab.
```
- Systematized Nomenclature of Medicine Clinical
    Terms (SNOMED CT) U.S. Edition, March 2023
    Release


##### Data Element Applicable Vocabulary Standard(s)

```
Standards listed are required. If more than one is
listed, at least one is required unless otherwise
noted. If a cell is empty, an applicable vocabulary
standard has not been identified.
```
##### Result Status

```
State or condition of a laboratory test.
```
##### Result Unit of Measure

```
Unit of measurement to report quantitative
laboratory test results.
```
- The Unified Code of Units for Measure,
    Revision 2.

##### Result Reference Range

```
Upper and lower limit of quantitative test values
expected for a designated population of
individuals.
```
```
Usage note: Reference range values may differ
by patient characteristics, laboratory test
manufacturer, and laboratory test performer.
```
- The Unified Code of Units for Measure,
    Revision 2. 1

##### Result Interpretation

```
Categorical assessment of a laboratory value,
often in relation to a test's reference range.
```
```
Examples include but are not limited to high, low,
critical high, and normal.
```
- Systematized Nomenclature of Medicine Clinical
    Terms (SNOMED CT) U.S. Edition, March 2023
    Release
- Health Level 7® (HL7) Code System
    ObservationInterpretation

##### Specimen Source Site

```
Body location from where a specimen was
obtained.
```
```
Examples include but are not limited to right
internal jugular, left arm, and right eye.
```
- Systematized Nomenclature of Medicine Clinical
    Terms (SNOMED CT) U.S. Edition, March 2023
    Release

##### Specimen Identifier

```
Sequence of characters assigned by a laboratory
for an individual specimen.
```
```
Example includes but is not limited to accession
number.
```
##### Specimen Condition Acceptability

```
Information regarding a specimen, including the
container, that does not meet a laboratory’s
criteria for acceptability.
```
```
Examples include but are not limited to
hemolyzed, clotted, container leaking, and
missing patient name.
```
```
Usage note: This may include information about
the contents of the container, the container, and
the label.
```
- Systematized Nomenclature of Medicine Clinical
    Terms (SNOMED CT) U.S. Edition, March 2023
    Release
- Health Level 7 (HL7) Code System
    SpecimenCondition


```
DATA CLASS
```
#### MEDICAL DEVICES

An instrument, machine, appliance, implant, software, or other article intended to be used for a medical
purpose.

##### Data Element Applicable Vocabulary Standard(s)

```
Standards listed are required. If more than one is
listed, at least one is required unless otherwise
noted. If a cell is empty, an applicable vocabulary
standard has not been identified.
```
##### Unique Device Identifier - Implantable

```
Numeric or alphanumeric code that uniquely
identifies an implantable device.
```
```
Usage note: Contains a device identifier (DI) and
one or more production identifiers (PI).
```
- FDA Unique Device Identification (UDI) System

```
DATA CLASS
```
#### MEDICATIONS

Pharmacologic agents used in the diagnosis, cure, mitigation, treatment, or prevention of disease.

##### Data Element Applicable Vocabulary Standard(s)

```
Standards listed are required. If more than one is
listed, at least one is required unless otherwise
noted. If a cell is empty, an applicable vocabulary
standard has not been identified.
```
##### Medications

```
Pharmacologic agent used in the diagnosis, cure,
mitigation, treatment, or prevention of disease.
```
- RxNorm Full Monthly Release, July 3, 2023
Optional:
- National Drug Code (NDC), July 20, 2023

##### Dose

```
Amount of a medication for each administration.
```
##### Dose Unit of Measure

```
Units of measure of a medication.
```
```
Examples include but are not limited to milligram
(mg) and milliliter (mL).
```
- The Unified Code for Units of Measure, Revision
    2.

##### Indication

```
Sign, symptom, or medical condition that is the
reason for giving or taking a medication.
```
- Systematized Nomenclature of Medicine Clinical
    Terms (SNOMED CT) U.S. Edition, March 2023
    Release
- International Classification of Diseases, Tenth
    Revision, Clinical Modification (ICD-10-CM)


##### Data Element Applicable Vocabulary Standard(s)

```
Standards listed are required. If more than one is
listed, at least one is required unless otherwise
noted. If a cell is empty, an applicable vocabulary
standard has not been identified.
```
##### Fill Status

```
State of a medication with regards to dispensing
or other activity.
```
```
Examples include but are not limited to
dispensed, partially dispensed, and not
dispensed.
```
##### Medication Instructions

```
Directions for administering or taking a
medication.
```
```
Examples include but are not limited to
prescription directions for taking a medication,
and package instructions for over-the-counter
medications.
```
```
Usage notes: May include route, quantity,
timing/frequency, and special instructions
(PRN, sliding scale, taper).
```
##### Medication Adherence

```
Statement of whether a medication has been
consumed according to instructions.
```
```
Examples include but are not limited to taking as
directed, taking less than directed, and not
taking.
```
- Systematized Nomenclature of Medicine Clinical
    Terms (SNOMED CT) U.S. Edition, March 2023
    Release

```
DATA CLASS
```
#### PATIENT DEMOGRAPHICS/INFORMATION

Data used to categorize individuals for identification, records matching, and other purposes.

##### Data Element Applicable Vocabulary Standard(s)

```
Standards listed are required. If more than one is
listed, at least one is required unless otherwise
noted. If a cell is empty, an applicable vocabulary
standard has not been identified.
```
##### First Name^

##### Last Name^


##### Data Element Applicable Vocabulary Standard(s)

```
Standards listed are required. If more than one is
listed, at least one is required unless otherwise
noted. If a cell is empty, an applicable vocabulary
standard has not been identified.
```
##### Middle Name

##### (Including middle initial)

##### Name Suffix

```
Name component following family name that
may be used to describe a person's position in a
family.
```
##### Previous Name^

##### Date of Birth

```
Known or estimated year, month, and day of the
patient's birth.
```
##### Date of Death

```
Known or estimated year, month, and day of the
patient's death.
```
##### Race Both standards are required:^

- The Office of Management and Budget
    Standards for Maintaining, Collecting, and
    Presenting Federal Data on Race and Ethnicity,
    Statistical Policy Directive No. 15, as revised,
    October 30, 1997
- CDC Race and Ethnicity Code Set Version 1.
    (July 2021)

##### Ethnicity Both standards are required:^

- The Office of Management and Budget
    Standards for Maintaining, Collecting, and
    Presenting Federal Data on Race and Ethnicity,
    Statistical Policy Directive No. 15, as revised,
    October 30, 1997
- CDC Race and Ethnicity Code Set Version 1.
    (July 2021)

##### Tribal Affiliation

Tribe or band with which an individual associates.


##### Data Element Applicable Vocabulary Standard(s)

```
Standards listed are required. If more than one is
listed, at least one is required unless otherwise
noted. If a cell is empty, an applicable vocabulary
standard has not been identified.
```
##### Sex

Documentation of a specific instance of sex.

- Both values must be supported:
- SNOMED CT U.S. Edition: 248152002
    (Female)
- SNOMED CT U.S. Edition: 248153007 (Male)

**Preferred Language** (^) • IETF (Internet Engineering Task Force)
Request for Comment (RFC) 5646, “Tags for
Identifying Languages”, September 2009

- Adopted at 45 CFR 170.207(g)(2)

##### Current Address

Place where a person is located or may be
contacted.

- Project US@ Technical Specification for Patient
    Addresses, Final Version 1.

##### Previous Address

Prior place where a person may have been
located or could have been contacted.

- Project US@ Technical Specification for Patient
    Addresses, Final Version 1.

##### Phone Number

```
Numbers and symbols to contact an individual
when using a phone.
```
```
Both standards are required:
```
- ITU-T E.123, Series E: Overall Network
    Operation, Telephone Service, Service
    Operation and Human Factors, International
    operation - General provisions concerning
    users: Notation for national and international
    telephone numbers, email addresses and web
    addresses, February 2001
- ITU-T E.164, Series E: Overall Network
    Operation, Telephone Service, Service
    Operation and Human Factors, International
    operation - Numbering plan of the international
    telephone service, The international public
    telecommunication numbering plan, November
    2010

```
Adopted at 45 CFR 170.207(q)(1)
```

##### Data Element Applicable Vocabulary Standard(s)

```
Standards listed are required. If more than one is
listed, at least one is required unless otherwise
noted. If a cell is empty, an applicable vocabulary
standard has not been identified.
```
##### Phone Number Type

```
Contact point when using a phone.
```
```
Examples include but are not limited to home,
work, and mobile.
```
##### Email Address

```
Unique identifier of an individual's email account
that is used to send and receive email
messages.
```
##### Related Person’s Name

```
Name of a person with a legal or familial
relationship to a patient.
```
##### Relationship Type

```
Relationship of a person to a patient.
```
```
Examples include but are not limited to parent,
next-of-kin, guardian, and custodian.
```
##### Occupation

```
Type of work of a person.
```
```
Examples include but are not limited to infantry,
business analyst, and social worker.
```
- Occupational Data for Health, version
    20201030

##### Occupation Industry

```
Type of business that compensates for work or
assigns work to an unpaid worker or volunteer.
```
```
Examples include but are not limited to U.S.
Army, cement manufacturing, and children and
youth services.
```
- Occupational Data for Health, version
    20201030


```
DATA CLASS
```
#### PATIENT SUMMARY AND PLAN

Conclusions and working assumptions that will guide treatment of the patient, and recommendations for
future treatment.

##### Data Element Applicable Vocabulary Standard(s)

```
Standards listed are required. If more than one is
listed, at least one is required unless otherwise
noted. If a cell is empty, an applicable vocabulary
standard has not been identified.
```
##### Assessment and Plan of Treatment

```
Health professional’s conclusions and working
assumptions that will guide treatment of the
patient.
```
```
DATA CLASS
```
#### PROBLEMS

Condition, diagnosis, or reason for seeking medical attention.

##### Data Element Applicable Vocabulary Standard(s)

```
Standards listed are required. If more than one is
listed, at least one is required unless otherwise
noted. If a cell is empty, an applicable vocabulary
standard has not been identified.
```
##### Problems

```
Condition, diagnosis, or reason for seeking
medical attention.
```
- Systematized Nomenclature of Medicine Clinical
    Terms (SNOMED CT) U.S. Edition, March 2023
    Release
- International Classification of Diseases, Tenth
    Revision, Clinical Modification (ICD-10-CM)
    2023

##### SDOH Problems/Health Concerns

```
Social Determinants of Health-related health
concerns, conditions, or diagnoses.
```
```
Examples include but are not limited to
homelessness and food insecurity.
```
- Systematized Nomenclature of Medicine Clinical
    Terms (SNOMED CT) U.S. Edition, March 2023
    Release
- International Classification of Diseases, Tenth
    Revision, Clinical Modification (ICD-10-CM)
    2023

##### Date of Diagnosis

```
Date of first determination by a qualified
professional of the presence of a problem or
condition affecting a patient.
```

##### Data Element Applicable Vocabulary Standard(s)

```
Standards listed are required. If more than one is
listed, at least one is required unless otherwise
noted. If a cell is empty, an applicable vocabulary
standard has not been identified.
```
##### Date of Resolution

```
Date of subsiding or termination of a symptom,
problem, or condition.
```
```
DATA CLASS
```
#### PROCEDURES

Activity performed for or on a patient as part of the provision of care.

##### Data Element Applicable Vocabulary Standard(s)

```
Standards listed are required. If more than one is
listed, at least one is required unless otherwise
noted. If a cell is empty, an applicable vocabulary
standard has not been identified.
```
##### Procedures

```
Activity performed for or on a patient as part of
the provision of care.
```
- Systematized Nomenclature of Medicine Clinical
    Terms (SNOMED CT) U.S. Edition, March 2023
    Release
- Current Procedural Terminology (CPT®) 2023,
    as maintained and distributed by the American
    Medical Association, for physician services and
    other health care services, and Healthcare
    Common Procedure Coding System (HCPCS),
    as maintained and distributed by HHS.
- For technology primarily developed to record
    dental procedures: Code on Dental Procedures
    and Nomenclature (CDT), maintained and
    distributed by the American Dental Association,
    for dental services.
Optional:
- International Classification of Diseases, Tenth
    Revision, Procedure Coding System (ICD-10-
    PCS) 2023

##### Performance Time

```
Time and/or date a procedure is performed.
```
```
Examples include but are not limited to vaccine
or medication administration times, surgery start
time, and time ultrasound performed.
```

##### Data Element Applicable Vocabulary Standard(s)

```
Standards listed are required. If more than one is
listed, at least one is required unless otherwise
noted. If a cell is empty, an applicable vocabulary
standard has not been identified.
```
##### SDOH Interventions

```
Actions or services to address an identified
Social Determinants of Health-related health
concern, condition, or diagnosis.
```
```
Examples include but are not limited to education
about food pantry program and referral to non-
emergency medical transportation program.
```
- Systematized Nomenclature of Medicine Clinical
    Terms (SNOMED CT) U.S. Edition, March 2023
    Release
- Current Procedural Terminology (CPT) 2023, as
    maintained and distributed by the American
    Medical Association, for physician services and
    other health care services.
- Healthcare Common Procedure Coding System
    (HCPCS) Level II, as maintained and distributed
    by HHS.

##### Reason for Referral

```
Explanation or justification for a referral or
consultation.
```
- Systematized Nomenclature of Medicine Clinical
    Terms (SNOMED CT) U.S. Edition, March 2023
    Release
- International Classification of Diseases, Tenth
    Revision, Clinical Modification (ICD-10-CM)
    2023

```
DATA CLASS
```
#### PROVENANCE

The metadata, or extra information about data, regarding who created the data and when it was created.

##### Data Element Applicable Vocabulary Standard(s)

```
Standards listed are required. If more than one is
listed, at least one is required unless otherwise
noted. If a cell is empty, an applicable vocabulary
standard has not been identified.
```
##### Author Time Stamp

```
Date and time of author action.
```
##### Author Organization

```
Organization associated with author.
```

```
DATA CLASS
```
#### VITAL SIGNS

Physiologic measurements of a patient that indicate the status of the body’s life sustaining functions.

##### Data Element Applicable Vocabulary Standard(s)

```
Standards listed are required. If more than one is
listed, at least one is required unless otherwise
noted. If a cell is empty, an applicable vocabulary
standard has not been identified.
```
##### Systolic Blood Pressure Both standards are required.^

- Logical Observation Identifiers Names and
    Codes (LOINC) version 2.74
- The Unified Code of Units for Measure,
    Revision 2.1

##### Diastolic Blood Pressure Both standards are required.^

- Logical Observation Identifiers Names and
    Codes (LOINC) version 2.74
- The Unified Code of Units for Measure,
    Revision 2.1

##### Average Blood Pressure

```
Arithmetic average of systolic and diastolic
components of two of more blood pressure
readings in a specified time period or according
to a specified algorithm or protocol.
```
```
Examples include but are not limited to 3-day
morning and evening home monitoring, clinical
encounter repeat average, and 24-hour
ambulatory measurement.
```
```
Both standards are required.
```
- Logical Observation Identifiers Names and
    Codes (LOINC) version 2.74
- The Unified Code of Units for Measure,
    Revision 2.1

##### Heart Rate Both standards are required.^

- Logical Observation Identifiers Names and
    Codes (LOINC) version 2.74
- The Unified Code of Units for Measure,
    Revision 2.1

##### Respiratory Rate Both standards are required.^

- Logical Observation Identifiers Names and
    Codes (LOINC) version 2.74
- The Unified Code of Units for Measure,
    Revision 2.1


##### Data Element Applicable Vocabulary Standard(s)

```
Standards listed are required. If more than one is
listed, at least one is required unless otherwise
noted. If a cell is empty, an applicable vocabulary
standard has not been identified.
```
##### Body Temperature Both standards are required.^

- Logical Observation Identifiers Names and
    Codes (LOINC) version 2.74
- The Unified Code of Units for Measure,
    Revision 2.1

##### Body Height Both standards are required.^

- Logical Observation Identifiers Names and
    Codes (LOINC) version 2.74
- The Unified Code of Units for Measure,
    Revision 2.1

##### Body Weight Both standards are required.^

- Logical Observation Identifiers Names and
    Codes (LOINC) version 2.74
- The Unified Code of Units for Measure,
    Revision 2.1

##### Pulse Oximetry Both standards are required.^

- Logical Observation Identifiers Names and
    Codes (LOINC) version 2.74
- The Unified Code of Units for Measure,
    Revision 2.1

##### Inhaled Oxygen Concentration Both standards are required.^

- Logical Observation Identifiers Names and
    Codes (LOINC) version 2.74
- The Unified Code of Units for Measure,
    Revision 2.1

##### BMI Percentile (2 - 20 years) Both standards are required.^

- Logical Observation Identifiers Names and
    Codes (LOINC) version 2.74
- The Unified Code of Units for Measure,
    Revision 2.1


##### Data Element Applicable Vocabulary Standard(s)

```
Standards listed are required. If more than one is
listed, at least one is required unless otherwise
noted. If a cell is empty, an applicable vocabulary
standard has not been identified.
```
##### Weight-for-length Percentile (Birth - 24

##### Months)

```
Both standards are required.
```
- Logical Observation Identifiers Names and
    Codes (LOINC) version 2.74
- The Unified Code of Units for Measure,
    Revision 2.1

##### Head Occipital-frontal Circumference

##### Percentile (Birth - 36 Months)

```
Both standards are required.
```
- Logical Observation Identifiers Names and
    Codes (LOINC) version 2.74
- The Unified Code of Units for Measure,
    Revision 2.1


### Changes between USCDI v4 and v4 (Errata)

##### Change Type Details

##### Change Applicable

##### Standards

- Specimen Condition Acceptability
    - Either SNOMED CT, U.S. Edition or HL7 Code System
       SpecimenCondition is required
- Result Interpretation
    - Either SNOMED CT, U.S. Edition or HL7 Code System
       ObservationInterpretation is required

##### Change Data Element

##### Definition

- Laboratory – Result Unit of Measure
- Limited to quantitative results

##### Correction to Changes

##### between USCDI v3 and

##### v4 table

- Removed Coverage Type, Functional Status, and Smoking Status from
    the Add Data Element list


### Changes between USCDI v3 and v4

##### Change Type Description of change

**Add Data Element** (^) • Substance (Non-Medication)

- Encounter Identifier
- Facility Identifier
- Facility Type
- Facility Name
- Treatment Intervention Preference
- Care Experience Preference
- Alcohol Use
- Substance Use
- Physical Activity
- Result Unit of Measure
- Result Reference Range
- Result Interpretation
- Specimen Source Site
- Specimen Identifier
- Specimen Condition Acceptability
- Medication Instructions
- Medication Adherence
- Performance Time
- Average Blood Pressure

**Add Data Class** (^) • Facility Information

##### Change Data Element

##### Name

- Rename Unique Device Identifier(s) for a Patient’s Implantable Device(s)
    to Unique Device Identifier Implantable

##### Change Data Class

##### Name

- Goals to Goals and Preferences
- Unique Device Identifier(s) for a Patient’s Implantable Device(s) to
    Medical Devices
- Assessment and Plan of Treatment to Patient Summary and Plan

##### Change Data Element

##### Definition

- Coverage Type
- Functional Status
- Smoking Status

##### Change Data Class

##### Definition

- Goals and Preferences


##### Change Type Description of change

##### Change Applicable

##### Standards

- Laboratory – Values/Results
- Medication - Indication
- Reason for Referral

##### Move Data Element to

##### Different Class

(Does not change or limit
how data element can be
used)

- SDOH Assessment moved from Assessment and Plan of Treatment to
    Health Status Assessment Data Class

##### Updated Applicable

##### Standards versions

- Systematized Nomenclature of Medicine Clinical Terms (SNOMED CT)
    U.S. Edition, March 2023 Release
- Logical Observation Identifiers Names and Codes (LOINC) version 2.74
- International Classification of Diseases, Tenth Revision, Clinical
    Modification (ICD-10-CM) 2023
- International Classification of Diseases, Tenth Revision, Procedure
    Coding System (ICD-10-PCS) 2023.
- Current Procedural Terminology (CPT) 2023
- Healthcare Common Procedure Coding System (HCPCS) Level II 2023
- RxNorm Full Monthly Release, July 3, 2023
- National Drug Code (NDC), July 20, 2023
- IIS: Current HL7 Standard Code Set, CVX – Vaccines Administered,
    updates through June 6, 2023
- Vaccine National Drug Code (NDC) Directory – Vaccine NDC Linker
    Table, updates through June 7, 2023


### Changes between USCDI Draft v4 and Final v4

##### Change Type Description of change

##### Change Data Element

##### Name

- Rename Specimen Condition and Disposition to Specimen Condition
    Acceptability
- Rename Time of Procedure to Performance Time

##### Change Data Element

##### Definition

- Facility Type
- Treatment Intervention Preference
- Care Experience Preference
- Physical Activity
- Result Unit of Measure
- Result Reference Range
- Result Interpretation
- Specimen Condition Acceptability
- Specimen Identifier
- Medication Instructions
- Medication Adherence
- Performance Time
- Average Blood Pressure
- Coverage Type
- Functional Status
- Smoking Status

##### Change Data Class

##### Definition

- Goals and Preferences

##### Change Applicable

##### Standards

- Result Reference Range
- Result Interpretation
- Specimen Condition Acceptability
- Medication Adherence
- Indication
- Reason for Referral


### Changes between USCDI v4 Errata (October 2023) and USCDI v4 Errata

### (March 2025)

##### Change Type Description of change

##### Changed data element

##### definition and applicable

##### vocabulary standard

- Sex

**Removed data elements** (^) • Sexual Orientation

- Gender Identity


