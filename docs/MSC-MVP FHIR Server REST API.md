openapi: 3.0.3
info:
  title: MSC-MVP FHIR Server REST API
  description: Complete RESTful interactions for a FHIR-compliant server including read, create, update, delete, search, history, transaction, and extensions for wound care and vascular compliance.
  version: 1.0.0
servers:
  - url: https://api.msc-mvp.com/fhir
    description: MSC-MVP FHIR Endpoint
paths:
  /Patient:
    post:
      summary: Create a Patient resource
      requestBody:
        required: true
        content:
          application/fhir+json:
            schema:
              $ref: '#/components/schemas/Patient'
      responses:
        '201':
          description: Patient created
    get:
      summary: Search Patient resources
      parameters:
        - in: query
          name: name
          schema:
            type: string
        - in: query
          name: birthdate
          schema:
            type: string
      responses:
        '200':
          description: A Bundle of Patient resources
          content:
            application/fhir+json:
              schema:
                $ref: '#/components/schemas/Bundle'
  /Patient/{id}:
    get:
      summary: Read a Patient resource
      parameters:
        - in: path
          name: id
          required: true
          schema:
            type: string
      responses:
        '200':
          description: Patient found
          content:
            application/fhir+json:
              schema:
                $ref: '#/components/schemas/Patient'
    put:
      summary: Update a Patient resource
      parameters:
        - in: path
          name: id
          required: true
          schema:
            type: string
      requestBody:
        required: true
        content:
          application/fhir+json:
            schema:
              $ref: '#/components/schemas/Patient'
      responses:
        '200':
          description: Patient updated
    patch:
      summary: Patch a Patient resource
      parameters:
        - in: path
          name: id
          required: true
          schema:
            type: string
      requestBody:
        required: true
        content:
          application/fhir+json:
            schema:
              type: object
      responses:
        '200':
          description: Patient patched
    delete:
      summary: Delete a Patient resource
      parameters:
        - in: path
          name: id
          required: true
          schema:
            type: string
      responses:
        '204':
          description: Patient deleted
  /Patient/{id}/_history:
    get:
      summary: View version history for a Patient resource
      parameters:
        - in: path
          name: id
          required: true
          schema:
            type: string
      responses:
        '200':
          description: Bundle with version history
          content:
            application/fhir+json:
              schema:
                $ref: '#/components/schemas/Bundle'
  /Patient/_history:
    get:
      summary: View system-wide Patient resource history
      responses:
        '200':
          description: History Bundle
          content:
            application/fhir+json:
              schema:
                $ref: '#/components/schemas/Bundle'
  /:
    post:
      summary: Perform batch or transaction
      requestBody:
        required: true
        content:
          application/fhir+json:
            schema:
              $ref: '#/components/schemas/Bundle'
      responses:
        '200':
          description: Bundle with batch/transaction outcomes
          content:
            application/fhir+json:
              schema:
                $ref: '#/components/schemas/Bundle'
  /metadata:
    get:
      summary: Retrieve server capability statement
      responses:
        '200':
          description: CapabilityStatement resource
          content:
            application/fhir+json:
              schema:
                $ref: '#/components/schemas/CapabilityStatement'
components:
  schemas:
    Patient:
      type: object
      description: FHIR Patient resource with MSC extensions
      properties:
        resourceType:
          type: string
          example: Patient
        id:
          type: string
        name:
          type: array
          items:
            type: object
            properties:
              family:
                type: string
              given:
                type: array
                items:
                  type: string
        gender:
          type: string
        birthDate:
          type: string
          format: date
        extension:
          type: array
          items:
            type: object
    Bundle:
      type: object
      description: FHIR Bundle resource
      properties:
        resourceType:
          type: string
          example: Bundle
        type:
          type: string
        entry:
          type: array
          items:
            type: object
    CapabilityStatement:
      type: object
      description: CapabilityStatement resource
      properties:
        resourceType:
          type: string
          example: CapabilityStatement
        status:
          type: string
        kind:
          type: string
        fhirVersion:
          type: string
