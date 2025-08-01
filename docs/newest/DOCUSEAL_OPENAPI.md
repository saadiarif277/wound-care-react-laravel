---
openapi: 3.0.0
servers:
- url: https://api.docuseal.com
  description: Global Server
- url: https://api.docuseal.eu
  description: EU Server
info:
  version: 1.0.9
  title: DocuSeal API
  description: DocuSeal API specs
  contact:
    name: DocuSeal
    email: admin@docuseal.com
    url: https://www.docuseal.com
components:
  securitySchemes:
    AuthToken:
      type: apiKey
      in: header
      name: X-Auth-Token
tags:
- name: Submissions
  description: Signature requests can be initiated with Submissions API. Submissions
    can contain one submitter if signed by a single party or multiple submitters if
    the document template form contains signatures and fields to be collected and
    filled by multiple parties. Initiate new submissions to request signatures for
    specified submitters via email or phone number.
- name: Submitters
  description: Submitters API allows you to load all details provided by the signer
    of the document.
- name: Templates
  description: Templates represent reusable document signing forms with fields and
    signatures to be collected. It's possible to create unique template forms with
    fields and signatures using HTML or with tagged PDFs.
paths:
  "/templates":
    get:
      security:
      - AuthToken: []
      tags:
      - Templates
      summary: List all templates
      description: The API endpoint provides the ability to retrieve a list of available
        document templates.
      operationId: getTemplates
      parameters:
      - name: q
        in: query
        required: false
        schema:
          type: string
        description: Filter templates based on the name partial match.
      - name: slug
        in: query
        required: false
        schema:
          type: string
        description: Filter templates by unique slug.
        example: opaKWh8WWTAcVG
      - name: external_id
        in: query
        required: false
        schema:
          type: string
        description: The unique applications-specific identifier provided for the
          template via API or Embedded template form builder. It allows you to receive
          only templates with your specified external id.
      - name: folder
        in: query
        required: false
        schema:
          type: string
        description: Filter templates by folder name.
      - name: archived
        in: query
        required: false
        schema:
          type: boolean
        description: Get only archived templates instead of active ones.
      - name: limit
        in: query
        required: false
        schema:
          type: integer
        description: The number of templates to return. Default value is 10. Maximum
          value is 100.
      - name: after
        in: query
        required: false
        schema:
          type: integer
        description: The unique identifier of the template to start the list from.
          It allows you to receive only templates with id greater than the specified
          value. Pass ID value from the `pagination.next` response to load the next
          batch of templates.
      - name: before
        in: query
        required: false
        schema:
          type: integer
        description: The unique identifier of the template to end the list with. It
          allows you to receive only templates with id less than the specified value.
      responses:
        '200':
          description: OK
          content:
            application/json:
              schema:
                type: object
                required:
                - data
                - pagination
                properties:
                  data:
                    type: array
                    description: List of templates.
                    items:
                      type: object
                      required:
                      - id
                      - slug
                      - name
                      - preferences
                      - schema
                      - fields
                      - submitters
                      - author_id
                      - created_at
                      - updated_at
                      - source
                      - external_id
                      - folder_id
                      - folder_name
                      - author
                      - documents
                      properties:
                        id:
                          type: integer
                          description: Unique identifier of the document template.
                        slug:
                          type: string
                          description: Unique slug of the document template.
                        name:
                          type: string
                          description: Name of the template.
                        preferences:
                          type: object
                          description: Template preferences.
                        schema:
                          type: array
                          description: List of documents attached to the template.
                          items:
                            type: object
                            required:
                            - attachment_uuid
                            - name
                            properties:
                              attachment_uuid:
                                type: string
                                description: Unique indentifier of attached document
                                  to the template.
                              name:
                                type: string
                                description: Name of the attached document to the
                                  template.
                        fields:
                          type: array
                          description: List of fields to be filled in the template.
                          items:
                            type: object
                            required:
                            - uuid
                            - submitter_uuid
                            - name
                            - type
                            - required
                            - areas
                            properties:
                              uuid:
                                type: string
                                description: Unique identifier of the field.
                              submitter_uuid:
                                type: string
                                description: Unique identifier of the submitter that
                                  filled the field.
                              name:
                                type: string
                                description: Field name.
                              type:
                                type: string
                                description: Type of the field (e.g., text, signature,
                                  date, initials).
                                enum:
                                - heading
                                - text
                                - signature
                                - initials
                                - date
                                - number
                                - image
                                - checkbox
                                - multiple
                                - file
                                - radio
                                - select
                                - cells
                                - stamp
                                - payment
                                - phone
                                - verification
                              required:
                                type: boolean
                                description: Indicates if the field is required.
                              preferences:
                                type: object
                                properties:
                                  font_size:
                                    type: integer
                                    description: Font size of the field value in pixels.
                                  font_type:
                                    type: string
                                    description: Font type of the field value.
                                  font:
                                    type: string
                                    description: Font family of the field value.
                                  color:
                                    type: string
                                    description: Font color of the field value.
                                  align:
                                    type: string
                                    description: Horizontal alignment of the field
                                      text value.
                                  valign:
                                    type: string
                                    description: Vertical alignment of the field text
                                      value.
                                  format:
                                    type: string
                                    description: The data format for different field
                                      types.
                                  price:
                                    type: number
                                    description: Price value of the payment field.
                                      Only for payment fields.
                                  currency:
                                    type: string
                                    description: Currency value of the payment field.
                                      Only for payment fields.
                                  mask:
                                    type: boolean
                                    description: Indicates if the field is masked
                                      on the document.
                              areas:
                                type: array
                                description: List of areas where the field is located
                                  in the document.
                                items:
                                  type: object
                                  required:
                                  - x
                                  - "y"
                                  - w
                                  - h
                                  - attachment_uuid
                                  - page
                                  properties:
                                    x:
                                      type: number
                                      description: X coordinate of the area where
                                        the field is located in the document.
                                    "y":
                                      type: number
                                      description: Y coordinate of the area where
                                        the field is located in the document.
                                    w:
                                      type: number
                                      description: Width of the area where the field
                                        is located in the document.
                                    h:
                                      type: number
                                      description: Height of the area where the field
                                        is located in the document.
                                    attachment_uuid:
                                      type: string
                                      description: Unique identifier of the attached
                                        document where the field is located.
                                    page:
                                      type: integer
                                      description: Page number of the attached document
                                        where the field is located.
                        submitters:
                          type: array
                          items:
                            type: object
                            required:
                            - name
                            - uuid
                            properties:
                              name:
                                type: string
                                description: Submitter name.
                              uuid:
                                type: string
                                description: Unique identifier of the submitter.
                        author_id:
                          type: integer
                          description: Unique identifier of the author of the template.
                        archived_at:
                          type: string
                          nullable: true
                          description: Date and time when the template was archived.
                        created_at:
                          type: string
                          description: Date and time when the template was created.
                        updated_at:
                          type: string
                          description: Date and time when the template was updated.
                        source:
                          type: string
                          description: Source of the template.
                          enum:
                          - native
                          - api
                          - embed
                        external_id:
                          type: string
                          nullable: true
                          description: Identifier of the template in the external
                            system.
                        folder_id:
                          type: integer
                          description: Unique identifier of the folder where the template
                            is placed.
                        folder_name:
                          type: string
                          description: Folder name where the template is placed.
                        author:
                          type: object
                          required:
                          - id
                          - first_name
                          - last_name
                          - email
                          properties:
                            id:
                              type: integer
                              description: Unique identifier of the author.
                            first_name:
                              type: string
                              description: First name of the author.
                            last_name:
                              type: string
                              description: Last name of the author.
                            email:
                              type: string
                              description: Author email.
                        documents:
                          type: array
                          description: List of documents attached to the template.
                          items:
                            type: object
                            required:
                            - id
                            - uuid
                            - url
                            - preview_image_url
                            - filename
                            properties:
                              id:
                                type: integer
                                description: Unique identifier of the document.
                              uuid:
                                type: string
                                description: Unique identifier of the document.
                              url:
                                type: string
                                description: URL of the document.
                              preview_image_url:
                                type: string
                                description: Document preview image URL.
                              filename:
                                type: string
                                description: Document filename.
                  pagination:
                    type: object
                    required:
                    - count
                    - next
                    - prev
                    properties:
                      count:
                        type: integer
                        description: Templates count.
                      next:
                        type: integer
                        nullable: true
                        description: The ID of the tempate after which the next page
                          starts.
                      prev:
                        type: integer
                        nullable: true
                        description: The ID of the tempate before which the previous
                          page ends.
              example:
                data:
                - id: 1
                  slug: iRgjDX7WDK6BRo
                  name: Example Template
                  preferences: {}
                  schema:
                  - attachment_uuid: d94e615f-76e3-46d5-8f98-36bdacb8664a
                    name: example-document
                  fields:
                  - uuid: 594bdf04-d941-4ca6-aa73-93e61d625c02
                    submitter_uuid: '0954d146-db8c-4772-aafe-2effc7c0e0c0'
                    name: Full Name
                    type: text
                    required: true
                    preferences: {}
                    areas:
                    - x: 0.2638888888888889
                      "y": 0.168958742632613
                      w: 0.325
                      h: 0.04616895874263263
                      attachment_uuid: d94e615f-76e3-46d5-8f98-36bdacb8664a
                      page: 0
                  submitters:
                  - name: First Party
                    uuid: '0954d146-db8c-4772-aafe-2effc7c0e0c0'
                  author_id: 1
                  archived_at:
                  created_at: '2023-12-14T15:21:57.375Z'
                  updated_at: '2023-12-14T15:22:55.094Z'
                  source: native
                  folder_id: 1
                  folder_name: Default
                  external_id: c248ffba-ef81-48b7-8e17-e3cecda1c1c5
                  author:
                    id: 1
                    first_name: John
                    last_name: Doe
                    email: john.doe@example.com
                  documents:
                  - id: 5
                    uuid: d94e615f-76e3-46d5-8f98-36bdacb8664a
                    url: https://docuseal.com/file/hash/sample-document.pdf
                    preview_image_url: https://docuseal.com/file/hash/0.jpg
                    filename: example-document.pdf
                pagination:
                  count: 1
                  next: 1
                  prev: 2
  "/templates/{id}":
    get:
      security:
      - AuthToken: []
      tags:
      - Templates
      summary: Get a template
      description: The API endpoint provides the functionality to retrieve information
        about a document template.
      operationId: getTemplate
      parameters:
      - name: id
        in: path
        required: true
        schema:
          type: integer
        description: The unique identifier of the document template.
        example: 1000001
      responses:
        '200':
          description: OK
          content:
            application/json:
              schema:
                type: object
                required:
                - id
                - slug
                - name
                - preferences
                - schema
                - fields
                - submitters
                - author_id
                - archived_at
                - created_at
                - updated_at
                - source
                - external_id
                - folder_id
                - folder_name
                - author
                - documents
                properties:
                  id:
                    type: integer
                    description: Unique identifier of the document template.
                  slug:
                    type: string
                    description: Unique slug of the document template.
                  name:
                    type: string
                    description: Name of the template.
                  preferences:
                    type: object
                    description: Template preferences.
                  schema:
                    type: array
                    description: List of documents attached to the template.
                    items:
                      type: object
                      required:
                      - attachment_uuid
                      - name
                      properties:
                        attachment_uuid:
                          type: string
                          description: Unique indentifier of attached document to
                            the template.
                        name:
                          type: string
                          description: Name of the attached document to the template.
                  fields:
                    type: array
                    description: List of fields to be filled in the template.
                    items:
                      type: object
                      required:
                      - uuid
                      - submitter_uuid
                      - name
                      - type
                      - required
                      - areas
                      properties:
                        uuid:
                          type: string
                          description: Unique identifier of the field.
                        submitter_uuid:
                          type: string
                          description: Unique identifier of the submitter that filled
                            the field.
                        name:
                          type: string
                          description: Field name.
                        type:
                          type: string
                          description: Type of the field (e.g., text, signature, date,
                            initials).
                          enum:
                          - heading
                          - text
                          - signature
                          - initials
                          - date
                          - number
                          - image
                          - checkbox
                          - multiple
                          - file
                          - radio
                          - select
                          - cells
                          - stamp
                          - payment
                          - phone
                          - verification
                        required:
                          type: boolean
                          description: Indicates if the field is required.
                        preferences:
                          type: object
                          properties:
                            font_size:
                              type: integer
                              description: Font size of the field value in pixels.
                            font_type:
                              type: string
                              description: Font type of the field value.
                            font:
                              type: string
                              description: Font family of the field value.
                            color:
                              type: string
                              description: Font color of the field value.
                            align:
                              type: string
                              description: Horizontal alignment of the field text
                                value.
                            valign:
                              type: string
                              description: Vertical alignment of the field text value.
                            format:
                              type: string
                              description: The data format for different field types.
                            price:
                              type: number
                              description: Price value of the payment field. Only
                                for payment fields.
                            currency:
                              type: string
                              description: Currency value of the payment field. Only
                                for payment fields.
                            mask:
                              type: boolean
                              description: Indicates if the field is masked on the
                                document.
                        areas:
                          type: array
                          description: List of areas where the field is located in
                            the document.
                          items:
                            type: object
                            required:
                            - x
                            - "y"
                            - w
                            - h
                            - attachment_uuid
                            - page
                            properties:
                              x:
                                type: number
                                description: X coordinate of the area where the field
                                  is located in the document.
                              "y":
                                type: number
                                description: Y coordinate of the area where the field
                                  is located in the document.
                              w:
                                type: number
                                description: Width of the area where the field is
                                  located in the document.
                              h:
                                type: number
                                description: Height of the area where the field is
                                  located in the document.
                              attachment_uuid:
                                type: string
                                description: Unique identifier of the attached document
                                  where the field is located.
                              page:
                                type: integer
                                description: Page number of the attached document
                                  where the field is located.
                  submitters:
                    type: array
                    items:
                      type: object
                      required:
                      - name
                      - uuid
                      properties:
                        name:
                          type: string
                          description: Submitter name.
                        uuid:
                          type: string
                          description: Unique identifier of the submitter.
                  author_id:
                    type: integer
                    description: Unique identifier of the author of the template.
                  archived_at:
                    type: string
                    nullable: true
                    description: Date and time when the template was archived.
                  created_at:
                    type: string
                    description: Date and time when the template was created.
                  updated_at:
                    type: string
                    description: Date and time when the template was updated.
                  source:
                    type: string
                    description: Source of the template.
                    enum:
                    - native
                    - api
                    - embed
                  external_id:
                    type: string
                    nullable: true
                    description: Identifier of the template in the external system.
                  folder_id:
                    type: integer
                    description: Unique identifier of the folder where the template
                      is placed.
                  folder_name:
                    type: string
                    description: Folder name where the template is placed.
                  author:
                    type: object
                    required:
                    - id
                    - first_name
                    - last_name
                    - email
                    properties:
                      id:
                        type: integer
                        description: Unique identifier of the author.
                      first_name:
                        type: string
                        description: First name of the author.
                      last_name:
                        type: string
                        description: Last name of the author.
                      email:
                        type: string
                        description: Author email.
                  documents:
                    type: array
                    description: List of documents attached to the template.
                    items:
                      type: object
                      required:
                      - id
                      - uuid
                      - url
                      - preview_image_url
                      - filename
                      properties:
                        id:
                          type: integer
                          description: Unique identifier of the document.
                        uuid:
                          type: string
                          description: Unique identifier of the document.
                        url:
                          type: string
                          description: URL of the document.
                        preview_image_url:
                          type: string
                          description: Document preview image URL.
                        filename:
                          type: string
                          description: Document filename.
              example:
                id: 1
                slug: iRgjDX7WDK6BRo
                name: Example Template
                preferences: {}
                schema:
                - attachment_uuid: d94e615f-76e3-46d5-8f98-36bdacb8664a
                  name: example-document
                fields:
                - uuid: 594bdf04-d941-4ca6-aa73-93e61d625c02
                  submitter_uuid: '0954d146-db8c-4772-aafe-2effc7c0e0c0'
                  name: Full Name
                  type: text
                  required: true
                  preferences: {}
                  areas:
                  - x: 0.2638888888888889
                    "y": 0.168958742632613
                    w: 0.325
                    h: 0.04616895874263263
                    attachment_uuid: d94e615f-76e3-46d5-8f98-36bdacb8664a
                    page: 0
                submitters:
                - name: First Party
                  uuid: '0954d146-db8c-4772-aafe-2effc7c0e0c0'
                author_id: 1
                archived_at:
                created_at: '2023-12-14T15:21:57.375Z'
                updated_at: '2023-12-14T15:22:55.094Z'
                source: native
                folder_id: 1
                folder_name: Default
                external_id: c248ffba-ef81-48b7-8e17-e3cecda1c1c5
                author:
                  id: 1
                  first_name: John
                  last_name: Doe
                  email: john.doe@example.com
                documents:
                - id: 5
                  uuid: d94e615f-76e3-46d5-8f98-36bdacb8664a
                  url: https://docuseal.com/file/hash/sample-document.pdf
                  preview_image_url: https://docuseal.com/file/hash/0.jpg
                  filename: example-document.pdf
    delete:
      security:
      - AuthToken: []
      tags:
      - Templates
      summary: Archive a template
      description: The API endpoint allows you to archive a document template.
      operationId: archiveTemplate
      parameters:
      - name: id
        in: path
        required: true
        schema:
          type: integer
        description: The unique identifier of the document template.
        example: 1000001
      responses:
        '200':
          description: OK
          content:
            application/json:
              schema:
                type: object
                required:
                - id
                - archived_at
                properties:
                  id:
                    type: integer
                    description: Template unique ID number.
                  archived_at:
                    type: string
                    nullable: true
                    description: Date and time when the template was archived.
              example:
                id: 1
                archived_at: '2023-12-14T15:50:21.799Z'
    put:
      security:
      - AuthToken: []
      tags:
      - Templates
      summary: Update a template
      description: The API endpoint provides the functionality to move a document
        template to a different folder and update the name of the template.
      operationId: updateTemplate
      parameters:
      - name: id
        in: path
        required: true
        schema:
          type: integer
        description: The unique identifier of the document template.
        example: 1000001
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              properties:
                name:
                  type: string
                  description: The name of the template
                  example: New Document Name
                folder_name:
                  type: string
                  description: The folder's name to which the template should be moved.
                  example: New Folder
                roles:
                  type: array
                  description: An array of submitter role names to update the template
                    with.
                  items:
                    type: string
                  example:
                  - Agent
                  - Customer
                archived:
                  type: boolean
                  description: Set `false` to unarchive template.
      responses:
        '200':
          description: OK
          content:
            application/json:
              schema:
                type: object
                required:
                - id
                - updated_at
                properties:
                  id:
                    type: integer
                    description: Template unique ID number.
                  updated_at:
                    type: string
                    description: Date and time when the template was last updated.
              example:
                id: 1
                updated_at: '2023-12-14T15:50:21.799Z'
  "/submissions":
    get:
      security:
      - AuthToken: []
      tags:
      - Submissions
      summary: List all submissions
      description: The API endpoint provides the ability to retrieve a list of available
        submissions.
      operationId: getSubmissions
      parameters:
      - name: template_id
        in: query
        required: false
        schema:
          type: integer
        description: The template ID allows you to receive only the submissions created
          from that specific template.
      - name: status
        in: query
        required: false
        schema:
          type: string
          enum:
          - pending
          - completed
          - declined
          - expired
        description: Filter submissions by status.
      - name: q
        in: query
        required: false
        schema:
          type: string
        description: Filter submissions based on submitters name, email or phone partial
          match.
      - name: slug
        in: query
        required: false
        schema:
          type: string
        description: Filter submissions by unique slug.
        example: NtLDQM7eJX2ZMd
      - name: template_folder
        in: query
        required: false
        schema:
          type: string
        description: Filter submissions by template folder name.
      - name: archived
        in: query
        required: false
        schema:
          type: boolean
        description: Returns only archived submissions when `true` and only active
          submissions when `false`.
      - name: limit
        in: query
        required: false
        schema:
          type: integer
        description: The number of submissions to return. Default value is 10. Maximum
          value is 100.
      - name: after
        in: query
        required: false
        schema:
          type: integer
        description: The unique identifier of the submission to start the list from.
          It allows you to receive only submissions with an ID greater than the specified
          value. Pass ID value from the `pagination.next` response to load the next
          batch of submissions.
      - name: before
        in: query
        required: false
        schema:
          type: integer
        description: The unique identifier of the submission that marks the end of
          the list. It allows you to receive only submissions with an ID less than
          the specified value.
      responses:
        '200':
          description: OK
          content:
            application/json:
              schema:
                type: object
                required:
                - data
                - pagination
                properties:
                  data:
                    type: array
                    items:
                      type: object
                      required:
                      - id
                      - source
                      - slug
                      - status
                      - submitters_order
                      - audit_log_url
                      - completed_at
                      - created_at
                      - updated_at
                      - submitters
                      - template
                      - created_by_user
                      properties:
                        id:
                          type: integer
                          description: Submission unique ID number.
                        source:
                          type: string
                          description: The source of the submission.
                          enum:
                          - invite
                          - bulk
                          - api
                          - embed
                          - link
                        slug:
                          type: string
                          description: Unique slug of the submission.
                        status:
                          type: string
                          description: The status of the submission.
                          enum:
                          - completed
                          - declined
                          - expired
                          - pending
                        submitters_order:
                          type: string
                          description: The order of submitters.
                          enum:
                          - random
                          - preserved
                        audit_log_url:
                          type: string
                          nullable: true
                          description: Audit log file URL.
                        combined_document_url:
                          type: string
                          nullable: true
                          description: Combined PDF file URL with documents and Audit
                            Log.
                        completed_at:
                          type: string
                          nullable: true
                          description: The date and time when the submission was completed.
                        created_at:
                          type: string
                          description: The date and time when the submission was created.
                        updated_at:
                          type: string
                          description: The date and time when the submission was last
                            updated.
                        archived_at:
                          type: string
                          nullable: true
                          description: The date and time when the submission was archived.
                        submitters:
                          type: array
                          description: The list of submitters.
                          items:
                            type: object
                            required:
                            - id
                            - submission_id
                            - uuid
                            - email
                            - slug
                            - sent_at
                            - opened_at
                            - completed_at
                            - declined_at
                            - created_at
                            - updated_at
                            - name
                            - phone
                            - status
                            - role
                            - metadata
                            - preferences
                            properties:
                              id:
                                type: integer
                                description: Submission unique ID number.
                              submission_id:
                                type: integer
                                description: Submission ID number.
                              uuid:
                                type: string
                                description: Submitter UUID.
                              email:
                                type: string
                                nullable: true
                                description: The email address of the submitter.
                              slug:
                                type: string
                                description: Unique key to be used in the form signing
                                  link and embedded form.
                              sent_at:
                                type: string
                                nullable: true
                                description: The date and time when the signing request
                                  was sent to the submitter.
                              opened_at:
                                type: string
                                nullable: true
                                description: The date and time when the submitter
                                  opened the signing form.
                              completed_at:
                                type: string
                                nullable: true
                                description: The date and time when the submitter
                                  completed the signing form.
                              declined_at:
                                type: string
                                nullable: true
                                description: The date and time when the submitter
                                  declined to complete the signing form.
                              created_at:
                                type: string
                                description: The date and time when the submitter
                                  was created.
                              updated_at:
                                type: string
                                description: The date and time when the submitter
                                  was last updated.
                              name:
                                type: string
                                nullable: true
                                description: Submitter name.
                              phone:
                                type: string
                                nullable: true
                                description: Submitter phone number.
                              external_id:
                                type: string
                                nullable: true
                                description: Your application-specific unique string
                                  key to identify this submitter within your app.
                              status:
                                type: string
                                description: The status of signing request for the
                                  submitter.
                                enum:
                                - completed
                                - declined
                                - opened
                                - sent
                                - awaiting
                              role:
                                type: string
                                description: The role of the submitter.
                              metadata:
                                type: object
                                description: Metadata object with additional submitter
                                  information.
                              preferences:
                                type: object
                                description: Object with submitter preferences.
                        template:
                          type: object
                          required:
                          - id
                          - name
                          - external_id
                          - folder_name
                          - created_at
                          - updated_at
                          properties:
                            id:
                              type: integer
                              description: Template unique ID number.
                            name:
                              type: string
                              description: The name of the submission template.
                            external_id:
                              type: string
                              nullable: true
                              description: Your application-specific unique string
                                key to identify this template within your app.
                            folder_name:
                              type: string
                              description: Folder name where the template is located.
                            created_at:
                              type: string
                              description: The date and time when the submission template
                                was created.
                            updated_at:
                              type: string
                              description: The date and time when the submission template
                                was last updated.
                        created_by_user:
                          type: object
                          nullable: true
                          required:
                          - id
                          - first_name
                          - last_name
                          - email
                          properties:
                            id:
                              type: integer
                              description: Unique identifier of the user who created
                                the submission.
                            first_name:
                              type: string
                              description: The first name of the user who created
                                the submission.
                            last_name:
                              type: string
                              description: The last name of the user who created the
                                submission.
                            email:
                              type: string
                              description: The email address of the user who created
                                the submission.
                  pagination:
                    type: object
                    required:
                    - count
                    - next
                    - prev
                    properties:
                      count:
                        type: integer
                        description: Submissions count.
                      next:
                        type: integer
                        nullable: true
                        description: The ID of the submission after which the next
                          page starts.
                      prev:
                        type: integer
                        nullable: true
                        description: The ID of the submission before which the previous
                          page ends.
              example:
                data:
                - id: 1
                  source: link
                  submitters_order: random
                  slug: VyL4szTwYoSvXq
                  status: completed
                  audit_log_url: https://docuseal.com/file/hash/example.pdf
                  combined_document_url:
                  expire_at:
                  completed_at: '2023-12-10T15:49:21.895Z'
                  created_at: '2023-12-10T15:48:17.166Z'
                  updated_at: '2023-12-10T15:49:21.895Z'
                  archived_at:
                  submitters:
                  - id: 1
                    submission_id: 1
                    uuid: '0954d146-db8c-4772-aafe-2effc7c0e0c0'
                    email: submitter@example.com
                    slug: dsEeWrhRD8yDXT
                    sent_at: '2023-12-14T15:45:49.011Z'
                    opened_at: '2023-12-14T15:48:23.011Z'
                    completed_at: '2023-12-14T15:49:21.701Z'
                    declined_at:
                    created_at: '2023-12-10T15:48:17.173Z'
                    updated_at: '2023-12-14T15:50:21.799Z'
                    name: John Doe
                    phone: "+1234567890"
                    status: completed
                    role: First Party
                    metadata: {}
                    preferences: {}
                  template:
                    id: 1
                    name: Example Template
                    external_id: Temp123
                    folder_name: Default
                    created_at: '2023-12-14T15:50:21.799Z'
                    updated_at: '2023-12-14T15:50:21.799Z'
                  created_by_user:
                    id: 1
                    first_name: Bob
                    last_name: Smith
                    email: bob.smith@example.com
                pagination:
                  count: 1
                  next: 1
                  prev: 1
    post:
      security:
      - AuthToken: []
      tags:
      - Submissions
      summary: Create a submission
      description: This API endpoint allows you to create signature requests (submissions)
        for a document template and send them to the specified submitters (signers).<br><b>Related
        Guides</b><br><a href="https://www.docuseal.com/guides/send-documents-for-signature-via-api"
        class="link">Send documents for signature via API</a><br><a href="https://www.docuseal.com/guides/pre-fill-pdf-document-form-fields-with-api"
        class="link">Pre-fill PDF document form fields with API</a>
      operationId: createSubmission
      parameters: []
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required:
              - template_id
              - submitters
              properties:
                template_id:
                  type: integer
                  description: The unique identifier of the template. Document template
                    forms can be created via the Web UI, <a href="https://www.docuseal.com/guides/use-embedded-text-field-tags-in-the-pdf-to-create-a-fillable-form"
                    class="link">PDF and DOCX API</a>, or <a href="https://www.docuseal.com/guides/create-pdf-document-fillable-form-with-html-api"
                    class="link">HTML API</a>.
                  example: 1000001
                send_email:
                  type: boolean
                  description: Set `false` to disable signature request emails sending.
                  default: true
                send_sms:
                  type: boolean
                  description: Set `true` to send signature request via phone number
                    and SMS.
                  default: false
                order:
                  type: string
                  description: Pass 'random' to send signature request emails to all
                    parties right away. The order is 'preserved' by default so the
                    second party will receive a signature request email only after
                    the document is signed by the first party.
                  default: preserved
                  enum:
                  - preserved
                  - random
                completed_redirect_url:
                  type: string
                  description: Specify URL to redirect to after the submission completion.
                bcc_completed:
                  type: string
                  description: Specify BCC address to send signed documents to after
                    the completion.
                reply_to:
                  type: string
                  description: Specify Reply-To address to use in the notification
                    emails.
                expire_at:
                  type: string
                  description: Specify the expiration date and time after which the
                    submission becomes unavailable for signature.
                  example: 2024-09-01 12:00:00 UTC
                message:
                  type: object
                  properties:
                    subject:
                      type: string
                      description: Custom signature request email subject.
                    body:
                      type: string
                      description: 'Custom signature request email body. Can include
                        the following variables: {{template.name}}, {{submitter.link}},
                        {{account.name}}.'
                submitters:
                  type: array
                  description: The list of submitters for the submission.
                  items:
                    type: object
                    required:
                    - email
                    properties:
                      name:
                        type: string
                        description: The name of the submitter.
                      role:
                        type: string
                        description: The role name or title of the submitter.
                        example: First Party
                      email:
                        type: string
                        description: The email address of the submitter.
                        format: email
                        example: john.doe@example.com
                      phone:
                        type: string
                        description: The phone number of the submitter, formatted
                          according to the E.164 standard.
                        example: "+1234567890"
                      values:
                        type: object
                        description: An object with pre-filled values for the submission.
                          Use field names for keys of the object. For more configurations
                          see `fields` param.
                      external_id:
                        type: string
                        description: Your application-specific unique string key to
                          identify this submitter within your app.
                      completed:
                        type: boolean
                        description: Pass `true` to mark submitter as completed and
                          auto-signed via API.
                      metadata:
                        type: object
                        description: Metadata object with additional submitter information.
                        example: '{ "customField": "value" }'
                      send_email:
                        type: boolean
                        description: Set `false` to disable signature request emails
                          sending only for this submitter.
                        default: true
                      send_sms:
                        type: boolean
                        description: Set `true` to send signature request via phone
                          number and SMS.
                        default: false
                      reply_to:
                        type: string
                        description: Specify Reply-To address to use in the notification
                          emails for this submitter.
                      completed_redirect_url:
                        type: string
                        description: Submitter specific URL to redirect to after the
                          submission completion.
                      message:
                        type: object
                        properties:
                          subject:
                            type: string
                            description: Custom signature request email subject for
                              the submitter.
                          body:
                            type: string
                            description: 'Custom signature request email body for
                              the submitter. Can include the following variables:
                              {{template.name}}, {{submitter.link}}, {{account.name}}.'
                      fields:
                        type: array
                        description: A list of configurations for template document
                          form fields.
                        items:
                          type: object
                          required:
                          - name
                          properties:
                            name:
                              type: string
                              description: Document template field name.
                              example: First Name
                            default_value:
                              oneOf:
                              - type: string
                              - type: number
                              - type: boolean
                              - type: array
                                items:
                                  oneOf:
                                  - type: string
                                  - type: number
                                  - type: boolean
                              description: Default value of the field. Use base64
                                encoded file or a public URL to the image file to
                                set default signature or image fields.
                              example: Acme
                            readonly:
                              type: boolean
                              description: Set `true` to make it impossible for the
                                submitter to edit predefined field value.
                              default: false
                            required:
                              type: boolean
                              description: Set `true` to make the field required.
                            title:
                              type: string
                              description: Field title displayed to the user instead
                                of the name, shown on the signing form. Supports Markdown.
                            description:
                              type: string
                              description: Field description displayed on the signing
                                form. Supports Markdown.
                            validation_pattern:
                              type: string
                              description: HTML field validation pattern string based
                                on https://developer.mozilla.org/en-US/docs/Web/HTML/Attributes/pattern
                                specification.
                              example: "[A-Z]{4}"
                            invalid_message:
                              type: string
                              description: A custom message to display on pattern
                                validation failure.
                            preferences:
                              type: object
                              properties:
                                font_size:
                                  type: integer
                                  description: Font size of the field value in pixels.
                                  example: 12
                                font_type:
                                  type: string
                                  description: Font type of the field value.
                                  enum:
                                  - bold
                                  - italic
                                  - bold_italic
                                font:
                                  type: string
                                  description: Font family of the field value.
                                  enum:
                                  - Times
                                  - Helvetica
                                  - Courier
                                color:
                                  type: string
                                  description: Font color of the field value.
                                  enum:
                                  - black
                                  - white
                                  - blue
                                  default: black
                                align:
                                  type: string
                                  description: Horizontal alignment of the field text
                                    value.
                                  enum:
                                  - left
                                  - center
                                  - right
                                  default: left
                                valign:
                                  type: string
                                  description: Vertical alignment of the field text
                                    value.
                                  enum:
                                  - top
                                  - center
                                  - bottom
                                  default: center
                                format:
                                  type: string
                                  description: 'The data format for different field
                                    types.<br>- Date field: accepts formats such as
                                    DD/MM/YYYY (default: MM/DD/YYYY).<br>- Signature
                                    field: accepts drawn, typed, drawn_or_typed (default),
                                    or upload.<br>- Number field: accepts currency
                                    formats such as usd, eur, gbp.'
                                  example: DD/MM/YYYY
                                price:
                                  type: number
                                  description: Price value of the payment field. Only
                                    for payment fields.
                                  example: 99.99
                                currency:
                                  type: string
                                  description: Currency value of the payment field.
                                    Only for payment fields.
                                  enum:
                                  - USD
                                  - EUR
                                  - GBP
                                  - CAD
                                  - AUD
                                  default: USD
                                mask:
                                  description: Set `true` to make sensitive data masked
                                    on the document.
                                  oneOf:
                                  - type: integer
                                  - type: boolean
                                  default: false
                      roles:
                        type: array
                        description: A list of roles for the submitter. Use this param
                          to merge multiple roles into one submitter.
                        items:
                          type: string
      responses:
        '200':
          description: OK
          content:
            application/json:
              schema:
                type: array
                items:
                  type: object
                  required:
                  - id
                  - submission_id
                  - uuid
                  - email
                  - slug
                  - status
                  - values
                  - metadata
                  - sent_at
                  - opened_at
                  - completed_at
                  - declined_at
                  - created_at
                  - updated_at
                  - name
                  - phone
                  - external_id
                  - preferences
                  - role
                  - embed_src
                  properties:
                    id:
                      type: integer
                      description: Submitter unique ID number.
                    submission_id:
                      type: integer
                      description: Submission UUID.
                    uuid:
                      type: string
                      description: Submitter UUID.
                    email:
                      type: string
                      nullable: true
                      description: The email address of the submitter.
                    slug:
                      type: string
                      description: Unique key to be used in the signing form URL.
                    status:
                      type: string
                      description: The status of signing request for the submitter.
                      enum:
                      - completed
                      - declined
                      - opened
                      - sent
                      - awaiting
                    values:
                      type: array
                      description: An array of pre-filled values for the submission.
                      items:
                        type: object
                        required:
                        - field
                        - value
                        properties:
                          field:
                            type: string
                            description: Document template field name.
                          value:
                            oneOf:
                            - type: string
                            - type: number
                            - type: boolean
                            - type: array
                              items:
                                oneOf:
                                - type: string
                                - type: number
                                - type: boolean
                            description: Pre-filled value of the field.
                    metadata:
                      type: object
                    sent_at:
                      type: string
                      nullable: true
                      description: The date and time when the signing request was
                        sent to the submitter.
                    opened_at:
                      type: string
                      nullable: true
                      description: The date and time when the submitter opened the
                        signing form.
                    completed_at:
                      type: string
                      nullable: true
                      description: The date and time when the submitter completed
                        the signing form.
                    declined_at:
                      type: string
                      nullable: true
                      description: The date and time when the submitter declined the
                        signing form.
                    created_at:
                      type: string
                      description: The date and time when the submitter was created.
                    updated_at:
                      type: string
                      description: The date and time when the submitter was last updated.
                    name:
                      type: string
                      nullable: true
                      description: The name of the submitter.
                    phone:
                      type: string
                      nullable: true
                      description: The phone number of the submitter.
                    external_id:
                      type: string
                      nullable: true
                      description: Your application-specific unique string key to
                        identify this submitter within your app.
                    preferences:
                      type: object
                      properties:
                        send_email:
                          type: boolean
                          description: Indicates whether the signature request email
                            should be sent.
                        send_sms:
                          type: boolean
                          description: Indicates whether the signature request should
                            be sent via SMS.
                    role:
                      type: string
                      description: The role of the submitter in the signing process.
                    embed_src:
                      type: string
                      description: The `src` URL value to embed the signing form or
                        sign via a link.
              example:
              - id: 1
                submission_id: 1
                uuid: 884d545b-3396-49f1-8c07-05b8b2a78755
                email: john.doe@example.com
                slug: pAMimKcyrLjqVt
                sent_at: '2023-12-13T23:04:04.252Z'
                opened_at:
                completed_at:
                declined_at:
                created_at: '2023-12-14T15:50:21.799Z'
                updated_at: '2023-12-14T15:50:21.799Z'
                name: string
                phone: "+1234567890"
                external_id: '2321'
                metadata:
                  customData: custom value
                status: sent
                values:
                - field: Full Name
                  value: John Doe
                preferences:
                  send_email: true
                  send_sms: false
                role: First Party
                embed_src: https://docuseal.com/s/pAMimKcyrLjqVt
  "/submissions/{id}":
    get:
      security:
      - AuthToken: []
      tags:
      - Submissions
      summary: Get a submission
      description: The API endpoint provides the functionality to retrieve information
        about a submission.
      operationId: getSubmission
      parameters:
      - name: id
        in: path
        required: true
        schema:
          type: integer
        description: The unique identifier of the submission.
        example: 1001
      responses:
        '200':
          description: OK
          content:
            application/json:
              schema:
                type: object
                required:
                - id
                - slug
                - source
                - submitters_order
                - audit_log_url
                - combined_document_url
                - created_at
                - updated_at
                - archived_at
                - submitters
                - template
                - created_by_user
                - submission_events
                - documents
                - status
                - metadata
                - completed_at
                properties:
                  id:
                    type: integer
                    description: Submission unique ID number.
                  slug:
                    type: string
                    description: Unique slug of the submission.
                  source:
                    type: string
                    description: The source of the submission.
                    enum:
                    - invite
                    - bulk
                    - api
                    - embed
                    - link
                  submitters_order:
                    type: string
                    description: The order of submitters.
                    enum:
                    - random
                    - preserved
                  audit_log_url:
                    type: string
                    nullable: true
                    description: Audit log file URL.
                  combined_document_url:
                    type: string
                    nullable: true
                    description: Combined PDF file URL with documents and Audit Log.
                  created_at:
                    type: string
                    description: The date and time when the submission was created.
                  updated_at:
                    type: string
                    description: The date and time when the submission was last updated.
                  archived_at:
                    type: string
                    nullable: true
                    description: The date and time when the submission was archived.
                  submitters:
                    type: array
                    description: The list of submitters.
                    items:
                      type: object
                      required:
                      - id
                      - submission_id
                      - uuid
                      - email
                      - slug
                      - sent_at
                      - opened_at
                      - completed_at
                      - declined_at
                      - created_at
                      - updated_at
                      - name
                      - phone
                      - external_id
                      - status
                      - values
                      - documents
                      - role
                      properties:
                        id:
                          type: integer
                          description: Submitter unique ID number.
                        submission_id:
                          type: integer
                          description: Submission unique ID number.
                        uuid:
                          type: string
                          description: Submitter UUID.
                        email:
                          type: string
                          nullable: true
                          description: The email address of the submitter.
                        slug:
                          type: string
                          description: Unique key to be used in the form signing link
                            and embedded form.
                        sent_at:
                          type: string
                          nullable: true
                          description: The date and time when the signing request
                            was sent to the submitter.
                        opened_at:
                          type: string
                          nullable: true
                          description: The date and time when the submitter opened
                            the signing form.
                        completed_at:
                          type: string
                          nullable: true
                          description: The date and time when the submitter completed
                            the signing form.
                        declined_at:
                          type: string
                          nullable: true
                          description: The date and time when the submitter declined
                            the signing form.
                        created_at:
                          type: string
                          description: The date and time when the submitter was created.
                        updated_at:
                          type: string
                          description: The date and time when the submitter was last
                            updated.
                        name:
                          type: string
                          nullable: true
                          description: Submitter name.
                        phone:
                          type: string
                          nullable: true
                          description: Submitter phone number.
                        external_id:
                          type: string
                          nullable: true
                          description: Your application-specific unique string key
                            to identify this submitter within your app.
                        status:
                          type: string
                          description: The status of signing request for the submitter.
                          enum:
                          - completed
                          - declined
                          - opened
                          - sent
                          - awaiting
                        values:
                          type: array
                          description: An array of field values for the submitter.
                          items:
                            type: object
                            required:
                            - field
                            - value
                            properties:
                              field:
                                type: string
                                description: Document template field name.
                              value:
                                oneOf:
                                - type: string
                                - type: number
                                - type: boolean
                                - type: array
                                  items:
                                    oneOf:
                                    - type: string
                                    - type: number
                                    - type: boolean
                                description: Pre-filled value of the field.
                        documents:
                          type: array
                          description: An array of completed or signed documents by
                            the submitter.
                          items:
                            type: object
                            required:
                            - name
                            - url
                            properties:
                              name:
                                type: string
                                description: Document name.
                              url:
                                type: string
                                description: Document URL.
                        role:
                          type: string
                          description: The role of the submitter in the signing process.
                  template:
                    type: object
                    required:
                    - id
                    - name
                    - external_id
                    - folder_name
                    - created_at
                    - updated_at
                    properties:
                      id:
                        type: integer
                        description: Template unique ID number.
                      name:
                        type: string
                        description: The name of the submission template.
                      external_id:
                        type: string
                        nullable: true
                        description: Your application-specific unique string key to
                          identify this template within your app.
                      folder_name:
                        type: string
                        description: Folder name where the template is located.
                      created_at:
                        type: string
                        description: The date and time when the submission template
                          was created.
                      updated_at:
                        type: string
                        description: The date and time when the submission template
                          was last updated.
                  created_by_user:
                    type: object
                    nullable: true
                    required:
                    - id
                    - first_name
                    - last_name
                    - email
                    properties:
                      id:
                        type: integer
                        description: Unique identifier of the user who created the
                          submission.
                      first_name:
                        type: string
                        description: The first name of the user who created the submission.
                      last_name:
                        type: string
                        description: The last name of the user who created the submission.
                      email:
                        type: string
                        description: The email address of the user who created the
                          submission.
                  submission_events:
                    type: array
                    items:
                      type: object
                      required:
                      - id
                      - submitter_id
                      - event_type
                      - event_timestamp
                      properties:
                        id:
                          type: integer
                          description: Submission event unique ID number.
                        submitter_id:
                          type: integer
                          description: Unique identifier of the submitter that triggered
                            the event.
                        event_type:
                          type: string
                          description: Event type.
                          enum:
                          - send_email
                          - send_reminder_email
                          - send_sms
                          - send_2fa_sms
                          - open_email
                          - click_email
                          - click_sms
                          - phone_verified
                          - start_form
                          - start_verification
                          - complete_verification
                          - view_form
                          - invite_party
                          - complete_form
                          - decline_form
                          - api_complete_form
                        event_timestamp:
                          type: string
                          description: Date and time when the event was triggered.
                  documents:
                    type: array
                    description: An array of completed or signed documents of the
                      submission.
                    items:
                      type: object
                      required:
                      - name
                      - url
                      properties:
                        name:
                          type: string
                          description: Document name.
                        url:
                          type: string
                          description: Document URL.
                  status:
                    type: string
                    description: The status of the submission.
                    enum:
                    - completed
                    - declined
                    - expired
                    - pending
                  metadata:
                    type: object
                    description: Object with custom metadata.
                  completed_at:
                    type: string
                    nullable: true
                    description: The date and time when the submission was fully completed.
              example:
                id: 1
                source: link
                submitters_order: random
                slug: VyL4szTwYoSvXq
                audit_log_url: https://docuseal.com/blobs/proxy/hash/example.pdf
                combined_document_url:
                completed_at: '2023-12-14T15:49:21.701Z'
                expire_at:
                created_at: '2023-12-10T15:48:17.166Z'
                updated_at: '2023-12-10T15:49:21.895Z'
                archived_at:
                submitters:
                - id: 1
                  submission_id: 1
                  uuid: '0954d146-db8c-4772-aafe-2effc7c0e0c0'
                  email: submitter@example.com
                  slug: dsEeWrhRD8yDXT
                  sent_at: '2023-12-14T15:45:49.011Z'
                  opened_at: '2023-12-14T15:48:23.011Z'
                  completed_at: '2023-12-14T15:49:21.701Z'
                  declined_at:
                  created_at: '2023-12-14T15:48:17.173Z'
                  updated_at: '2023-12-14T15:50:21.799Z'
                  name: John Doe
                  phone: "+1234567890"
                  external_id:
                  status: completed
                  metadata: {}
                  values:
                  - field: Full Name
                    value: John Doe
                  documents:
                  - name: example
                    url: https://docuseal.com/blobs/proxy/hash/example.pdf
                  role: First Party
                template:
                  id: 1
                  name: Example Template
                  external_id: Temp123
                  folder_name: Default
                  created_at: '2023-12-14T15:50:21.799Z'
                  updated_at: '2023-12-14T15:50:21.799Z'
                created_by_user:
                  id: 1
                  first_name: Bob
                  last_name: Smith
                  email: bob.smith@example.com
                submission_events:
                - id: 1
                  submitter_id: 2
                  event_type: view_form
                  event_timestamp: '2023-12-14T15:47:24.566Z'
                documents:
                - name: example
                  url: https://docuseal.com/file/hash/example.pdf
                status: completed
    delete:
      security:
      - AuthToken: []
      tags:
      - Submissions
      summary: Archive a submission
      description: The API endpoint allows you to archive a submission.
      operationId: archiveSubmission
      parameters:
      - name: id
        in: path
        required: true
        schema:
          type: integer
        description: The unique identifier of the submission.
        example: 1001
      responses:
        '200':
          description: OK
          content:
            application/json:
              schema:
                type: object
                required:
                - id
                - archived_at
                properties:
                  id:
                    type: integer
                    description: Submission unique ID number.
                  archived_at:
                    type: string
                    nullable: true
                    description: Date and time when the submission was archived.
              example:
                id: 1
                archived_at: '2023-12-14T15:50:21.799Z'
  "/submissions/{id}/documents":
    get:
      security:
      - AuthToken: []
      tags:
      - Submissions
      summary: Get submission documents
      description: This endpoint returns a list of partially filled documents for
        a submission. If the submission has been completed, the final signed documents
        are returned.
      operationId: getSubmissionDocuments
      parameters:
      - name: id
        in: path
        required: true
        schema:
          type: integer
        description: The unique identifier of the submission.
        example: 1001
      responses:
        '200':
          description: OK
          content:
            application/json:
              schema:
                type: object
                required:
                - id
                - documents
                properties:
                  id:
                    type: integer
                    description: Submission unique ID number.
                  documents:
                    type: array
                    items:
                      type: object
                      required:
                      - name
                      - url
                      properties:
                        name:
                          type: string
                          description: Document name.
                        url:
                          type: string
                          description: Document URL.
              example:
                id: 1
                documents:
                - name: example
                  url: https://docuseal.com/file/hash/example.pdf
  "/submissions/emails":
    post:
      security:
      - AuthToken: []
      tags:
      - Submissions
      summary: Create submissions from emails
      description: This API endpoint allows you to create submissions for a document
        template and send them to the specified email addresses. This is a simplified
        version of the POST /submissions API to be used with Zapier or other automation
        tools.
      operationId: createSubmissionsFromEmails
      parameters: []
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required:
              - template_id
              - emails
              properties:
                template_id:
                  type: integer
                  description: The unique identifier of the template.
                  example: 1000001
                emails:
                  type: string
                  description: A comma-separated list of email addresses to send the
                    submission to.
                  example: "{{emails}}"
                send_email:
                  type: boolean
                  description: Set `false` to disable signature request emails sending.
                  default: true
                message:
                  type: object
                  properties:
                    subject:
                      type: string
                      description: Custom signature request email subject.
                    body:
                      type: string
                      description: 'Custom signature request email body. Can include
                        the following variables: {{template.name}}, {{submitter.link}},
                        {{account.name}}.'
      responses:
        '200':
          description: OK
          content:
            application/json:
              schema:
                type: array
                items:
                  type: object
                  required:
                  - id
                  - submission_id
                  - uuid
                  - email
                  - slug
                  - status
                  - values
                  - metadata
                  - sent_at
                  - opened_at
                  - completed_at
                  - declined_at
                  - created_at
                  - updated_at
                  - name
                  - phone
                  - external_id
                  - preferences
                  - role
                  - embed_src
                  properties:
                    id:
                      type: integer
                      description: Submitter unique ID number.
                    submission_id:
                      type: integer
                      description: Submission UUID.
                    uuid:
                      type: string
                      description: Submitter UUID.
                    email:
                      type: string
                      nullable: true
                      description: The email address of the submitter.
                    slug:
                      type: string
                      description: Unique key to be used in the signing form URL.
                    status:
                      type: string
                      description: The status of signing request for the submitter.
                      enum:
                      - completed
                      - declined
                      - opened
                      - sent
                      - awaiting
                    values:
                      type: array
                      description: An array of pre-filled values for the submission.
                      items:
                        type: object
                        required:
                        - field
                        - value
                        properties:
                          field:
                            type: string
                            description: Document template field name.
                          value:
                            oneOf:
                            - type: string
                            - type: number
                            - type: boolean
                            - type: array
                              items:
                                oneOf:
                                - type: string
                                - type: number
                                - type: boolean
                            description: Pre-filled value of the field.
                    metadata:
                      type: object
                    sent_at:
                      type: string
                      nullable: true
                      description: The date and time when the signing request was
                        sent to the submitter.
                    opened_at:
                      type: string
                      nullable: true
                      description: The date and time when the submitter opened the
                        signing form.
                    completed_at:
                      type: string
                      nullable: true
                      description: The date and time when the submitter completed
                        the signing form.
                    declined_at:
                      type: string
                      nullable: true
                      description: The date and time when the submitter declined the
                        signing form.
                    created_at:
                      type: string
                      description: The date and time when the submitter was created.
                    updated_at:
                      type: string
                      description: The date and time when the submitter was last updated.
                    name:
                      type: string
                      nullable: true
                      description: The name of the submitter.
                    phone:
                      type: string
                      nullable: true
                      description: The phone number of the submitter.
                    external_id:
                      type: string
                      nullable: true
                      description: Your application-specific unique string key to
                        identify this submitter within your app.
                    preferences:
                      type: object
                      properties:
                        send_email:
                          type: boolean
                          description: Indicates whether the signature request email
                            should be sent.
                        send_sms:
                          type: boolean
                          description: Indicates whether the signature request should
                            be sent via SMS.
                    role:
                      type: string
                      description: The role of the submitter in the signing process.
                    embed_src:
                      type: string
                      description: The `src` URL value to embed the signing form or
                        sign via a link.
              example:
              - id: 1
                submission_id: 1
                uuid: 884d545b-3396-49f1-8c07-05b8b2a78755
                email: john.doe@example.com
                slug: pAMimKcyrLjqVt
                sent_at: '2023-12-13T23:04:04.252Z'
                opened_at:
                completed_at:
                declined_at:
                created_at: '2023-12-14T15:50:21.799Z'
                updated_at: '2023-12-14T15:50:21.799Z'
                name: string
                phone: "+1234567890"
                external_id: '2321'
                metadata:
                  customData: custom value
                status: sent
                values:
                - field: Full Name
                  value: John Doe
                preferences:
                  send_email: true
                  send_sms: false
                role: First Party
                embed_src: https://docuseal.com/s/pAMimKcyrLjqVt
              - id: 2
                submission_id: 1
                uuid: 884d545b-3396-49f1-8c07-05b8b2a78755
                email: alan.smith@example.com
                slug: SEwc65vHNDH3QS
                sent_at: '2023-12-13T23:04:04.252Z'
                opened_at:
                completed_at:
                declined_at:
                created_at: '2023-12-14T15:50:21.799Z'
                updated_at: '2023-12-14T15:50:21.799Z'
                name: string
                phone: "+1234567890"
                external_id: '2321'
                metadata:
                  customData: custom value
                status: sent
                values:
                - field: Full Name
                  value: Roe Moe
                preferences:
                  send_email: true
                  send_sms: false
                role: First Party
                embed_src: SEwc65vHNDH3QS
  "/submitters/{id}":
    get:
      security:
      - AuthToken: []
      tags:
      - Submitters
      summary: Get a submitter
      description: The API endpoint provides functionality to retrieve information
        about a submitter, along with the submitter documents and field values.
      operationId: getSubmitter
      parameters:
      - name: id
        in: path
        required: true
        schema:
          type: integer
        description: The unique identifier of the submitter.
        example: 500001
      responses:
        '200':
          description: OK
          content:
            application/json:
              schema:
                type: object
                required:
                - id
                - submission_id
                - uuid
                - email
                - slug
                - sent_at
                - opened_at
                - completed_at
                - declined_at
                - created_at
                - updated_at
                - name
                - phone
                - status
                - external_id
                - metadata
                - preferences
                - template
                - submission_events
                - values
                - documents
                - role
                properties:
                  id:
                    type: integer
                    description: Submitter unique ID number.
                  submission_id:
                    type: integer
                    description: Submission unique ID number.
                  uuid:
                    type: string
                    description: Submitter UUID.
                  email:
                    type: string
                    nullable: true
                    description: The email address of the submitter.
                  slug:
                    type: string
                    description: Unique key to be used in the form signing link and
                      embedded form.
                  sent_at:
                    type: string
                    nullable: true
                    description: The date and time when the signing request was sent
                      to the submitter.
                  opened_at:
                    type: string
                    nullable: true
                    description: The date and time when the submitter opened the signing
                      form.
                  completed_at:
                    type: string
                    nullable: true
                    description: The date and time when the submitter completed the
                      signing form.
                  declined_at:
                    type: string
                    nullable: true
                    description: The date and time when the submitter declined the
                      signing form.
                  created_at:
                    type: string
                    description: The date and time when the submitter was created.
                  updated_at:
                    type: string
                    description: The date and time when the submitter was last updated.
                  name:
                    type: string
                    nullable: true
                    description: Submitter name.
                  phone:
                    type: string
                    nullable: true
                    description: Submitter phone number.
                  status:
                    type: string
                    description: Submitter's submission status.
                    enum:
                    - completed
                    - declined
                    - opened
                    - sent
                    - awaiting
                  external_id:
                    type: string
                    nullable: true
                    description: The unique applications-specific identifier
                  metadata:
                    type: object
                    description: Metadata object with additional submitter information.
                  preferences:
                    type: object
                    description: Submitter preferences.
                  template:
                    type: object
                    required:
                    - id
                    - name
                    - created_at
                    - updated_at
                    description: Base template details.
                    properties:
                      id:
                        type: number
                        description: The template's unique identifier.
                      name:
                        type: string
                        description: The template's name.
                      created_at:
                        type: string
                        format: date-time
                      updated_at:
                        type: string
                        format: date-time
                  submission_events:
                    type: array
                    items:
                      type: object
                      required:
                      - id
                      - submitter_id
                      - event_type
                      - event_timestamp
                      properties:
                        id:
                          type: integer
                          description: Submission event unique ID number.
                        submitter_id:
                          type: integer
                          description: Unique identifier of the submitter that triggered
                            the event.
                        event_type:
                          type: string
                          description: Event type.
                          enum:
                          - send_email
                          - send_reminder_email
                          - send_sms
                          - send_2fa_sms
                          - open_email
                          - click_email
                          - click_sms
                          - phone_verified
                          - start_form
                          - start_verification
                          - complete_verification
                          - view_form
                          - invite_party
                          - complete_form
                          - decline_form
                          - api_complete_form
                        event_timestamp:
                          type: string
                          description: Date and time when the event was triggered.
                  values:
                    type: array
                    description: An array of pre-filled values for the submitter.
                    items:
                      type: object
                      required:
                      - field
                      - value
                      properties:
                        field:
                          type: string
                          description: Document template field name.
                        value:
                          oneOf:
                          - type: string
                          - type: number
                          - type: boolean
                          - type: array
                            items:
                              oneOf:
                              - type: string
                              - type: number
                              - type: boolean
                          description: Pre-filled value of the field.
                  documents:
                    type: array
                    description: An array of completed or signed documents by the
                      submitter.
                    items:
                      type: object
                      required:
                      - name
                      - url
                      properties:
                        name:
                          type: string
                          description: Document name.
                        url:
                          type: string
                          description: Document URL.
                  role:
                    type: string
                    description: The role of the submitter in the signing process.
              example:
                id: 7
                submission_id: 3
                uuid: '0954d146-db8c-4772-aafe-2effc7c0e0c0'
                email: submitter@example.com
                slug: dsEeWrhRD8yDXT
                sent_at: '2023-12-14T15:45:49.011Z'
                opened_at: '2023-12-14T15:48:23.011Z'
                completed_at: '2023-12-10T15:49:21.701Z'
                declined_at:
                created_at: '2023-12-14T15:48:17.173Z'
                updated_at: '2023-12-14T15:50:21.799Z'
                name: John Doe
                phone: "+1234567890"
                status: completed
                external_id:
                metadata: {}
                preferences: {}
                template:
                  id: 2
                  name: Example Template
                  created_at: '2023-12-14T15:50:21.799Z'
                  updated_at: '2023-12-14T15:50:21.799Z'
                submission_events:
                - id: 12
                  submitter_id: 7
                  event_type: view_form
                  event_timestamp: '2023-12-14T15:47:17.351Z'
                values:
                - field: Full Name
                  value: John Doe
                documents:
                - name: sample-document
                  url: https://docuseal.com/file/hash/sample-document.pdf
                role: First Party
    put:
      security:
      - AuthToken: []
      tags:
      - Submitters
      summary: Update a submitter
      description: The API endpoint allows you to update submitter details, pre-fill
        or update field values and re-send emails.<br><b>Related Guides</b><br><a
        href="https://www.docuseal.com/guides/pre-fill-pdf-document-form-fields-with-api#automatically_sign_documents_via_api"
        class="link">Automatically sign documents via API</a>
      operationId: updateSubmitter
      parameters:
      - name: id
        in: path
        required: true
        schema:
          type: integer
        description: The unique identifier of the submitter.
        example: 500001
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              properties:
                name:
                  type: string
                  description: The name of the submitter.
                email:
                  type: string
                  description: The email address of the submitter.
                  format: email
                  example: john.doe@example.com
                phone:
                  type: string
                  description: The phone number of the submitter, formatted according
                    to the E.164 standard.
                  example: "+1234567890"
                values:
                  type: object
                  description: An object with pre-filled values for the submission.
                    Use field names for keys of the object. For more configurations
                    see `fields` param.
                external_id:
                  type: string
                  description: Your application-specific unique string key to identify
                    this submitter within your app.
                send_email:
                  type: boolean
                  description: Set `true` to re-send signature request emails.
                send_sms:
                  type: boolean
                  description: Set `true` to re-send signature request via phone number
                    SMS.
                  default: false
                reply_to:
                  type: string
                  description: Specify Reply-To address to use in the notification
                    emails.
                completed_redirect_url:
                  type: string
                  description: Submitter specific URL to redirect to after the submission
                    completion.
                completed:
                  type: boolean
                  description: Pass `true` to mark submitter as completed and auto-signed
                    via API.
                metadata:
                  type: object
                  description: Metadata object with additional submitter information.
                  example: '{ "customField": "value" }'
                message:
                  type: object
                  properties:
                    subject:
                      type: string
                      description: Custom signature request email subject.
                    body:
                      type: string
                      description: 'Custom signature request email body. Can include
                        the following variables: {{template.name}}, {{submitter.link}},
                        {{account.name}}.'
                fields:
                  type: array
                  description: A list of configurations for template document form
                    fields.
                  items:
                    type: object
                    required:
                    - name
                    properties:
                      name:
                        type: string
                        description: Document template field name.
                        example: First Name
                      default_value:
                        oneOf:
                        - type: string
                        - type: number
                        - type: boolean
                        - type: array
                          items:
                            oneOf:
                            - type: string
                            - type: number
                            - type: boolean
                        description: Default value of the field. Use base64 encoded
                          file or a public URL to the image file to set default signature
                          or image fields.
                        example: Acme
                      readonly:
                        type: boolean
                        description: Set `true` to make it impossible for the submitter
                          to edit predefined field value.
                        default: false
                      required:
                        type: boolean
                        description: Set `true` to make the field required.
                      validation_pattern:
                        type: string
                        description: HTML field validation pattern string based on
                          https://developer.mozilla.org/en-US/docs/Web/HTML/Attributes/pattern
                          specification.
                        example: "[A-Z]{4}"
                      invalid_message:
                        type: string
                        description: A custom message to display on pattern validation
                          failure.
                      preferences:
                        type: object
                        properties:
                          font_size:
                            type: integer
                            description: Font size of the field value in pixels.
                            example: 12
                          font_type:
                            type: string
                            description: Font type of the field value.
                            enum:
                            - bold
                            - italic
                            - bold_italic
                          font:
                            type: string
                            description: Font family of the field value.
                            enum:
                            - Times
                            - Helvetica
                            - Courier
                          color:
                            type: string
                            description: Font color of the field value.
                            enum:
                            - black
                            - white
                            - blue
                            default: black
                          align:
                            type: string
                            description: Horizontal alignment of the field text value.
                            enum:
                            - left
                            - center
                            - right
                            default: left
                          valign:
                            type: string
                            description: Vertical alignment of the field text value.
                            enum:
                            - top
                            - center
                            - bottom
                            default: center
                          format:
                            type: string
                            description: 'The data format for different field types.<br>-
                              Date field: accepts formats such as DD/MM/YYYY (default:
                              MM/DD/YYYY).<br>- Signature field: accepts drawn, typed,
                              drawn_or_typed (default), or upload.<br>- Number field:
                              accepts currency formats such as usd, eur, gbp.'
                            example: DD/MM/YYYY
                          price:
                            type: number
                            description: Price value of the payment field. Only for
                              payment fields.
                            example: 99.99
                          currency:
                            type: string
                            description: Currency value of the payment field. Only
                              for payment fields.
                            enum:
                            - USD
                            - EUR
                            - GBP
                            - CAD
                            - AUD
                            default: USD
                          mask:
                            description: Set `true` to make sensitive data masked
                              on the document.
                            oneOf:
                            - type: integer
                            - type: boolean
                            default: false
      responses:
        '200':
          description: OK
          content:
            application/json:
              schema:
                type: object
                required:
                - id
                - submission_id
                - uuid
                - email
                - slug
                - sent_at
                - opened_at
                - completed_at
                - declined_at
                - created_at
                - updated_at
                - name
                - phone
                - status
                - external_id
                - metadata
                - preferences
                - values
                - documents
                - role
                - embed_src
                properties:
                  id:
                    type: integer
                    description: Submitter unique ID number.
                  submission_id:
                    type: integer
                    description: Submission unique ID number.
                  uuid:
                    type: string
                    description: Submitter UUID.
                  email:
                    type: string
                    nullable: true
                    description: The email address of the submitter.
                  slug:
                    type: string
                    description: Unique key to be used in the form signing link and
                      embedded form.
                  sent_at:
                    type: string
                    nullable: true
                    description: The date and time when the signing request was sent
                      to the submitter.
                  opened_at:
                    type: string
                    nullable: true
                    description: The date and time when the submitter opened the signing
                      form.
                  completed_at:
                    type: string
                    nullable: true
                    description: The date and time when the submitter completed the
                      signing form.
                  declined_at:
                    type: string
                    nullable: true
                    description: The date and time when the submitter declined the
                      signing form.
                  created_at:
                    type: string
                    description: The date and time when the submitter was created.
                  updated_at:
                    type: string
                    description: The date and time when the submitter was last updated.
                  name:
                    type: string
                    nullable: true
                    description: Submitter name.
                  phone:
                    type: string
                    nullable: true
                    description: Submitter phone number.
                  status:
                    type: string
                    description: Submitter's submission status.
                    enum:
                    - completed
                    - declined
                    - opened
                    - sent
                    - awaiting
                  external_id:
                    type: string
                    nullable: true
                    description: The unique applications-specific identifier
                  metadata:
                    type: object
                    description: Metadata object with additional submitter information.
                  preferences:
                    type: object
                    description: Submitter preferences.
                  values:
                    type: array
                    description: An array of pre-filled values for the submitter.
                    items:
                      type: object
                      required:
                      - field
                      - value
                      properties:
                        field:
                          type: string
                          description: Document template field name.
                        value:
                          oneOf:
                          - type: string
                          - type: number
                          - type: boolean
                          - type: array
                            items:
                              oneOf:
                              - type: string
                              - type: number
                              - type: boolean
                          description: Pre-filled value of the field.
                  documents:
                    type: array
                    description: An array of completed or signed documents by the
                      submitter.
                    items:
                      type: object
                      required:
                      - name
                      - url
                      properties:
                        name:
                          type: string
                          description: Document name.
                        url:
                          type: string
                          description: Document URL.
                  role:
                    type: string
                    description: The role of the submitter in the signing process.
                  embed_src:
                    type: string
                    description: The `src` URL value to embed the signing form or
                      sign via a link.
              example:
                id: 1
                submission_id: 12
                uuid: '0954d146-db8c-4772-aafe-2effc7c0e0c0'
                email: submitter@example.com
                slug: dsEeWrhRD8yDXT
                sent_at: '2023-12-14T15:45:49.011Z'
                opened_at: '2023-12-14T15:48:23.011Z'
                completed_at: '2023-12-10T15:49:21.701Z'
                declined_at:
                created_at: '2023-12-14T15:48:17.173Z'
                updated_at: '2023-12-14T15:50:21.799Z'
                name: John Doe
                phone: "+1234567890"
                status: completed
                external_id:
                metadata: {}
                preferences: {}
                values:
                - field: Full Name
                  value: John Doe
                documents: []
                role: First Party
                embed_src: https://docuseal.com/s/pAMimKcyrLjqVt
  "/submitters":
    get:
      security:
      - AuthToken: []
      tags:
      - Submitters
      summary: List all submitters
      description: The API endpoint provides the ability to retrieve a list of submitters.
      operationId: getSubmitters
      parameters:
      - name: submission_id
        in: query
        required: false
        schema:
          type: integer
        description: The submission ID allows you to receive only the submitters related
          to that specific submission.
      - name: q
        in: query
        required: false
        schema:
          type: string
        description: Filter submitters on name, email or phone partial match.
      - name: slug
        in: query
        required: false
        schema:
          type: string
        description: Filter submitters by unique slug.
        example: zAyL9fH36Havvm
      - name: completed_after
        in: query
        required: false
        schema:
          type: string
          format: date-time
        example: '2024-03-05 9:32:20'
        description: The date and time string value to filter submitters that completed
          the submission after the specified date and time.
      - name: completed_before
        in: query
        required: false
        schema:
          type: string
          format: date-time
        example: '2024-03-06 19:32:20'
        description: The date and time string value to filter submitters that completed
          the submission before the specified date and time.
      - name: external_id
        in: query
        required: false
        schema:
          type: string
        description: The unique applications-specific identifier provided for a submitter
          when initializing a signature request. It allows you to receive only submitters
          with a specified external id.
      - name: limit
        in: query
        required: false
        schema:
          type: integer
        description: The number of submitters to return. Default value is 10. Maximum
          value is 100.
      - name: after
        in: query
        required: false
        schema:
          type: integer
        description: The unique identifier of the submitter to start the list from.
          It allows you to receive only submitters with id greater than the specified
          value. Pass ID value from the `pagination.next` response to load the next
          batch of submitters.
      - name: before
        in: query
        required: false
        schema:
          type: integer
        description: The unique identifier of the submitter to end the list with.
          It allows you to receive only submitters with id less than the specified
          value.
      responses:
        '200':
          description: OK
          content:
            application/json:
              schema:
                type: object
                properties:
                  data:
                    type: array
                    items:
                      type: object
                      required:
                      - id
                      - submission_id
                      - uuid
                      - email
                      - slug
                      - sent_at
                      - opened_at
                      - completed_at
                      - declined_at
                      - created_at
                      - updated_at
                      - name
                      - phone
                      - status
                      - external_id
                      - preferences
                      - metadata
                      - submission_events
                      - values
                      - documents
                      - role
                      properties:
                        id:
                          type: integer
                          description: Submitter unique ID number.
                        submission_id:
                          type: integer
                          description: Submission unique ID number.
                        uuid:
                          type: string
                          description: Submitter UUID.
                        email:
                          type: string
                          description: The email address of the submitter.
                        slug:
                          type: string
                          description: Unique slug of the submitter form.
                        sent_at:
                          type: string
                          nullable: true
                          description: The date and time when the signing request
                            was sent to the submitter.
                        opened_at:
                          type: string
                          nullable: true
                          description: The date and time when the submitter opened
                            the signing form.
                        completed_at:
                          type: string
                          nullable: true
                          description: The date and time when the submitter completed
                            the signing form.
                        declined_at:
                          type: string
                          nullable: true
                          description: The date and time when the submitter declined
                            the signing form.
                        created_at:
                          type: string
                          description: The date and time when the submitter was created.
                        updated_at:
                          type: string
                          description: The date and time when the submitter was last
                            updated.
                        name:
                          type: string
                          nullable: true
                          description: Submitter name.
                        phone:
                          type: string
                          nullable: true
                          description: Submitter phone number.
                        status:
                          type: string
                          description: Submitter's submission status.
                          enum:
                          - completed
                          - declined
                          - opened
                          - sent
                          - awaiting
                        external_id:
                          type: string
                          nullable: true
                          description: The unique applications-specific identifier
                        preferences:
                          type: object
                          description: Submitter preferences.
                        metadata:
                          type: object
                          description: Metadata object with additional submitter information.
                        submission_events:
                          type: array
                          items:
                            type: object
                            required:
                            - id
                            - submitter_id
                            - event_type
                            - event_timestamp
                            properties:
                              id:
                                type: integer
                                description: Unique identifier of the submission event.
                              submitter_id:
                                type: integer
                                description: Unique identifier of the submitter that
                                  triggered the event.
                              event_type:
                                type: string
                                description: Event type.
                                enum:
                                - send_email
                                - send_reminder_email
                                - send_sms
                                - send_2fa_sms
                                - open_email
                                - click_email
                                - click_sms
                                - phone_verified
                                - start_form
                                - start_verification
                                - complete_verification
                                - view_form
                                - invite_party
                                - complete_form
                                - decline_form
                                - api_complete_form
                              event_timestamp:
                                type: string
                                description: Date and time when the event was triggered.
                        values:
                          type: array
                          description: An array of pre-filled values for the submission.
                          items:
                            type: object
                            required:
                            - field
                            - value
                            properties:
                              field:
                                type: string
                                description: Document template field name.
                              value:
                                oneOf:
                                - type: string
                                - type: number
                                - type: boolean
                                - type: array
                                  items:
                                    oneOf:
                                    - type: string
                                    - type: number
                                    - type: boolean
                                description: Pre-filled value of the field.
                        documents:
                          type: array
                          description: An array of completed or signed documents by
                            the submitter.
                          items:
                            type: object
                            required:
                            - name
                            - url
                            properties:
                              name:
                                type: string
                                description: Document name.
                              url:
                                type: string
                                description: Document URL.
                        role:
                          type: string
                          description: The role of the submitter in the signing process.
                  pagination:
                    type: object
                    required:
                    - count
                    - next
                    - prev
                    properties:
                      count:
                        type: integer
                        description: Submitters count.
                      next:
                        type: integer
                        nullable: true
                        description: The ID of the submitter after which the next
                          page starts.
                      prev:
                        type: integer
                        nullable: true
                        description: The ID of the submitter before which the previous
                          page ends.
              example:
                data:
                - id: 7
                  submission_id: 3
                  uuid: '0954d146-db8c-4772-aafe-2effc7c0e0c0'
                  email: submitter@example.com
                  slug: dsEeWrhRD8yDXT
                  sent_at: '2023-12-14T15:45:49.011Z'
                  opened_at: '2023-12-14T15:48:23.011Z'
                  completed_at: '2023-12-14T15:49:21.701Z'
                  declined_at:
                  created_at: '2023-12-14T15:48:17.173Z'
                  updated_at: '2023-12-14T15:50:21.799Z'
                  name: John Doe
                  phone: "+1234567890"
                  status: completed
                  external_id:
                  preferences: {}
                  metadata: {}
                  template:
                    id: 2
                    name: Example Template
                    created_at: '2023-12-14T15:50:21.799Z'
                    updated_at: '2023-12-14T15:50:21.799Z'
                  submission_events:
                  - id: 12
                    submitter_id: 7
                    event_type: view_form
                    event_timestamp: '2023-12-14T15:48:17.351Z'
                  values:
                  - field: Full Name
                    value: John Doe
                  documents:
                  - name: sample-document
                    url: https://docuseal.com/file/eyJfcmFpbHMiOnsiIiwiZXhwIjpudWxsLCJwdXIiOiJibG9iX2lkIn19--f9758362acced0f3c86cdffad02800e/sample-document.pdf
                  role: First Party
                pagination:
                  count: 1
                  next: 1
                  prev: 1
  "/templates/{id}/documents":
    put:
      security:
      - AuthToken: []
      tags:
      - Templates
      summary: Update template documents
      description: The API endpoint allows you to add, remove or replace documents
        in the template with provided PDF/DOCX file or HTML content.
      operationId: addDocumentToTemplate
      parameters:
      - name: id
        in: path
        required: true
        schema:
          type: integer
        description: The unique identifier of the documents template.
        example: 1000001
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              properties:
                documents:
                  type: array
                  description: The list of documents to add or replace in the template.
                  items:
                    type: object
                    properties:
                      name:
                        type: string
                        description: Document name. Random uuid will be assigned when
                          not specified.
                        example: Test Template
                      file:
                        type: string
                        format: base64
                        description: Base64-encoded content of the PDF or DOCX file
                          or downloadable file URL. Leave it empty if you create a
                          new document using HTML param.
                      html:
                        type: string
                        description: HTML template with field tags. Leave it empty
                          if you add a document via PDF or DOCX base64 encoded file
                          param or URL.
                      position:
                        type: integer
                        description: Position of the document. By default will be
                          added as the last document in the template.
                        example: 0
                      replace:
                        type: boolean
                        default: false
                        description: Set to `true` to replace existing document with
                          a new file at `position`. Existing document fields will
                          be transferred to the new document if it doesn't contain
                          any fields.
                      remove:
                        type: boolean
                        default: false
                        description: Set to `true` to remove existing document at
                          given `position` or with given `name`.
                merge:
                  type: boolean
                  default: false
                  description: Set to `true` to merge all existing and new documents
                    into a single PDF document in the template.
      responses:
        '200':
          description: OK
          content:
            application/json:
              schema:
                type: object
                required:
                - id
                - slug
                - name
                - preferences
                - schema
                - fields
                - submitters
                - author_id
                - archived_at
                - created_at
                - updated_at
                - source
                - external_id
                - folder_id
                - folder_name
                - author
                - documents
                properties:
                  id:
                    type: integer
                    description: Unique identifier of the document template.
                  slug:
                    type: string
                    description: Unique slug of the document template.
                  name:
                    type: string
                    description: Name of the template.
                  preferences:
                    type: object
                    description: Template preferences.
                  schema:
                    type: array
                    description: List of documents attached to the template.
                    items:
                      type: object
                      required:
                      - attachment_uuid
                      - name
                      properties:
                        attachment_uuid:
                          type: string
                          description: Unique indentifier of attached document to
                            the template.
                        name:
                          type: string
                          description: Name of the attached document to the template.
                  fields:
                    type: array
                    description: List of fields to be filled in the template.
                    items:
                      type: object
                      required:
                      - uuid
                      - submitter_uuid
                      - name
                      - type
                      - required
                      - areas
                      properties:
                        uuid:
                          type: string
                          description: Unique identifier of the field.
                        submitter_uuid:
                          type: string
                          description: Unique identifier of the submitter that filled
                            the field.
                        name:
                          type: string
                          description: Field name.
                        type:
                          type: string
                          description: Type of the field (e.g., text, signature, date,
                            initials).
                          enum:
                          - heading
                          - text
                          - signature
                          - initials
                          - date
                          - number
                          - image
                          - checkbox
                          - multiple
                          - file
                          - radio
                          - select
                          - cells
                          - stamp
                          - payment
                          - phone
                          - verification
                        required:
                          type: boolean
                          description: Indicates if the field is required.
                        preferences:
                          type: object
                          properties:
                            font_size:
                              type: integer
                              description: Font size of the field value in pixels.
                            font_type:
                              type: string
                              description: Font type of the field value.
                            font:
                              type: string
                              description: Font family of the field value.
                            color:
                              type: string
                              description: Font color of the field value.
                            align:
                              type: string
                              description: Horizontal alignment of the field text
                                value.
                            valign:
                              type: string
                              description: Vertical alignment of the field text value.
                            format:
                              type: string
                              description: The data format for different field types.
                            price:
                              type: number
                              description: Price value of the payment field. Only
                                for payment fields.
                            currency:
                              type: string
                              description: Currency value of the payment field. Only
                                for payment fields.
                            mask:
                              type: boolean
                              description: Indicates if the field is masked on the
                                document.
                        areas:
                          type: array
                          description: List of areas where the field is located in
                            the document.
                          items:
                            type: object
                            required:
                            - x
                            - "y"
                            - w
                            - h
                            - attachment_uuid
                            - page
                            properties:
                              x:
                                type: number
                                description: X coordinate of the area where the field
                                  is located in the document.
                              "y":
                                type: number
                                description: Y coordinate of the area where the field
                                  is located in the document.
                              w:
                                type: number
                                description: Width of the area where the field is
                                  located in the document.
                              h:
                                type: number
                                description: Height of the area where the field is
                                  located in the document.
                              attachment_uuid:
                                type: string
                                description: Unique identifier of the attached document
                                  where the field is located.
                              page:
                                type: integer
                                description: Page number of the attached document
                                  where the field is located.
                  submitters:
                    type: array
                    items:
                      type: object
                      required:
                      - name
                      - uuid
                      properties:
                        name:
                          type: string
                          description: Submitter name.
                        uuid:
                          type: string
                          description: Unique identifier of the submitter.
                  author_id:
                    type: integer
                    description: Unique identifier of the author of the template.
                  archived_at:
                    type: string
                    nullable: true
                    description: Date and time when the template was archived.
                  created_at:
                    type: string
                    description: Date and time when the template was created.
                  updated_at:
                    type: string
                    description: Date and time when the template was updated.
                  source:
                    type: string
                    description: Source of the template.
                    enum:
                    - native
                    - api
                    - embed
                  external_id:
                    type: string
                    nullable: true
                    description: Identifier of the template in the external system.
                  folder_id:
                    type: integer
                    description: Unique identifier of the folder where the template
                      is placed.
                  folder_name:
                    type: string
                    description: Folder name where the template is placed.
                  author:
                    type: object
                    required:
                    - id
                    - first_name
                    - last_name
                    - email
                    properties:
                      id:
                        type: integer
                        description: Unique identifier of the author.
                      first_name:
                        type: string
                        description: First name of the author.
                      last_name:
                        type: string
                        description: Last name of the author.
                      email:
                        type: string
                        description: Author email.
                  documents:
                    type: array
                    description: List of documents attached to the template.
                    items:
                      type: object
                      required:
                      - id
                      - uuid
                      - url
                      - preview_image_url
                      - filename
                      properties:
                        id:
                          type: integer
                          description: Unique identifier of the document.
                        uuid:
                          type: string
                          description: Unique identifier of the document.
                        url:
                          type: string
                          description: URL of the document.
                        preview_image_url:
                          type: string
                          description: Document preview image URL.
                        filename:
                          type: string
                          description: Document filename.
              example:
                id: 3
                slug: ZQpF222rFBv71q
                name: Demo Template
                schema:
                - name: Demo Template
                  attachment_uuid: '09a8bc73-a7a9-4fd9-8173-95752bdf0af5'
                fields:
                - name: Name
                  required: false
                  type: text
                  uuid: a06c49f6-4b20-4442-ac7f-c1040d2cf1ac
                  submitter_uuid: 93ba628c-5913-4456-a1e9-1a81ad7444b3
                  areas:
                  - page: 0
                    attachment_uuid: '09a8bc73-a7a9-4fd9-8173-95752bdf0af5'
                    x: 0.403158189124654
                    "y": 0.04211750189825361
                    w: 0.100684625476058
                    h: 0.01423690205011389
                submitters:
                - name: Submitter
                  uuid: 93ba628c-5913-4456-a1e9-1a81ad7444b3
                author_id: 1
                archived_at:
                created_at: '2023-12-14T15:50:21.799Z'
                updated_at: '2023-12-14T15:50:21.799Z'
                source: api
                folder_id: 1
                folder_name: Default
                external_id: f0b4714f-e44b-4993-905b-68b4451eef8c
                author:
                  id: 1
                  first_name: John
                  last_name: Doe
                  email: john.doe@example.com
                documents:
                - id: 3
                  uuid: '09a8bc73-a7a9-4fd9-8173-95752bdf0af5'
                  url: https://docuseal.com/file/hash/Test%20Template.pdf
  "/templates/{id}/clone":
    post:
      security:
      - AuthToken: []
      tags:
      - Templates
      summary: Clone a template
      description: The API endpoint allows you to clone existing template into a new
        template.
      operationId: cloneTemplate
      parameters:
      - name: id
        in: path
        required: true
        schema:
          type: integer
        description: The unique identifier of the documents template.
        example: 1000001
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              properties:
                name:
                  type: string
                  description: Template name. Existing name with (Clone) suffix will
                    be used if not specified.
                  example: Cloned Template
                folder_name:
                  type: string
                  description: The folder's name to which the template should be cloned.
                external_id:
                  type: string
                  description: Your application-specific unique string key to identify
                    this template within your app.
      responses:
        '200':
          description: OK
          content:
            application/json:
              schema:
                type: object
                required:
                - id
                - slug
                - name
                - preferences
                - schema
                - fields
                - submitters
                - author_id
                - archived_at
                - created_at
                - updated_at
                - source
                - external_id
                - folder_id
                - folder_name
                - author
                - documents
                properties:
                  id:
                    type: integer
                    description: Unique identifier of the document template.
                  slug:
                    type: string
                    description: Unique slug of the document template.
                  name:
                    type: string
                    description: Name of the template.
                  preferences:
                    type: object
                    description: Template preferences.
                  schema:
                    type: array
                    description: List of documents attached to the template.
                    items:
                      type: object
                      required:
                      - attachment_uuid
                      - name
                      properties:
                        attachment_uuid:
                          type: string
                          description: Unique indentifier of attached document to
                            the template.
                        name:
                          type: string
                          description: Name of the attached document to the template.
                  fields:
                    type: array
                    description: List of fields to be filled in the template.
                    items:
                      type: object
                      required:
                      - uuid
                      - submitter_uuid
                      - name
                      - type
                      - required
                      - areas
                      properties:
                        uuid:
                          type: string
                          description: Unique identifier of the field.
                        submitter_uuid:
                          type: string
                          description: Unique identifier of the submitter that filled
                            the field.
                        name:
                          type: string
                          description: Field name.
                        type:
                          type: string
                          description: Type of the field (e.g., text, signature, date,
                            initials).
                          enum:
                          - heading
                          - text
                          - signature
                          - initials
                          - date
                          - number
                          - image
                          - checkbox
                          - multiple
                          - file
                          - radio
                          - select
                          - cells
                          - stamp
                          - payment
                          - phone
                          - verification
                        required:
                          type: boolean
                          description: Indicates if the field is required.
                        preferences:
                          type: object
                          properties:
                            font_size:
                              type: integer
                              description: Font size of the field value in pixels.
                            font_type:
                              type: string
                              description: Font type of the field value.
                            font:
                              type: string
                              description: Font family of the field value.
                            color:
                              type: string
                              description: Font color of the field value.
                            align:
                              type: string
                              description: Horizontal alignment of the field text
                                value.
                            valign:
                              type: string
                              description: Vertical alignment of the field text value.
                            format:
                              type: string
                              description: The data format for different field types.
                            price:
                              type: number
                              description: Price value of the payment field. Only
                                for payment fields.
                            currency:
                              type: string
                              description: Currency value of the payment field. Only
                                for payment fields.
                            mask:
                              type: boolean
                              description: Indicates if the field is masked on the
                                document.
                        areas:
                          type: array
                          description: List of areas where the field is located in
                            the document.
                          items:
                            type: object
                            required:
                            - x
                            - "y"
                            - w
                            - h
                            - attachment_uuid
                            - page
                            properties:
                              x:
                                type: number
                                description: X coordinate of the area where the field
                                  is located in the document.
                              "y":
                                type: number
                                description: Y coordinate of the area where the field
                                  is located in the document.
                              w:
                                type: number
                                description: Width of the area where the field is
                                  located in the document.
                              h:
                                type: number
                                description: Height of the area where the field is
                                  located in the document.
                              attachment_uuid:
                                type: string
                                description: Unique identifier of the attached document
                                  where the field is located.
                              page:
                                type: integer
                                description: Page number of the attached document
                                  where the field is located.
                  submitters:
                    type: array
                    items:
                      type: object
                      required:
                      - name
                      - uuid
                      properties:
                        name:
                          type: string
                          description: Submitter name.
                        uuid:
                          type: string
                          description: Unique identifier of the submitter.
                  author_id:
                    type: integer
                    description: Unique identifier of the author of the template.
                  archived_at:
                    type: string
                    nullable: true
                    description: Date and time when the template was archived.
                  created_at:
                    type: string
                    description: Date and time when the template was created.
                  updated_at:
                    type: string
                    description: Date and time when the template was updated.
                  source:
                    type: string
                    description: Source of the template.
                    enum:
                    - native
                    - api
                    - embed
                  external_id:
                    type: string
                    nullable: true
                    description: Identifier of the template in the external system.
                  folder_id:
                    type: integer
                    description: Unique identifier of the folder where the template
                      is placed.
                  folder_name:
                    type: string
                    description: Folder name where the template is placed.
                  author:
                    type: object
                    required:
                    - id
                    - first_name
                    - last_name
                    - email
                    properties:
                      id:
                        type: integer
                        description: Unique identifier of the author.
                      first_name:
                        type: string
                        description: First name of the author.
                      last_name:
                        type: string
                        description: Last name of the author.
                      email:
                        type: string
                        description: Author email.
                  documents:
                    type: array
                    description: List of documents attached to the template.
                    items:
                      type: object
                      required:
                      - id
                      - uuid
                      - url
                      - preview_image_url
                      - filename
                      properties:
                        id:
                          type: integer
                          description: Unique identifier of the document.
                        uuid:
                          type: string
                          description: Unique identifier of the document.
                        url:
                          type: string
                          description: URL of the document.
                        preview_image_url:
                          type: string
                          description: Document preview image URL.
                        filename:
                          type: string
                          description: Document filename.
              example:
                id: 6
                slug: Xc7opTqwwV9P7x
                name: Cloned Template
                schema:
                - attachment_uuid: 68aa0716-61f0-4535-9ba4-6c00f835b080
                  name: sample-document
                fields:
                - uuid: 93c7065b-1b19-4551-b67b-9946bf1c11c9
                  submitter_uuid: ad128012-756d-4d17-b728-6f6b1d482bb5
                  name: Name
                  type: text
                  required: true
                  areas:
                  - page: 0
                    attachment_uuid: '09a8bc73-a7a9-4fd9-8173-95752bdf0af5'
                    x: 0.403158189124654
                    "y": 0.04211750189825361
                    w: 0.100684625476058
                    h: 0.01423690205011389
                submitters:
                - name: First Party
                  uuid: ad128012-756d-4d17-b728-6f6b1d482bb5
                author_id: 1
                archived_at:
                created_at: '2023-12-14T15:50:21.799Z'
                updated_at: '2023-12-14T15:50:21.799Z'
                source: api
                folder_id: 2
                folder_name: Default
                external_id:
                author:
                  id: 1
                  first_name: John
                  last_name: Doe
                  email: john.doe@example.com
                documents:
                - id: 9
                  uuid: ded62277-9705-4fac-b5dc-58325d4102eb
                  url: https:/docuseal.com/file/hash/sample-document.pdf
                  filename: sample-document.pdf
  "/templates/html":
    post:
      security:
      - AuthToken: []
      tags:
      - Templates
      summary: Create a template from HTML
      description: The API endpoint provides the functionality to seamlessly generate
        a PDF document template by utilizing the provided HTML content while incorporating
        pre-defined fields.<br><b>Related Guides</b><br><a href="https://www.docuseal.com/guides/create-pdf-document-fillable-form-with-html-api"
        class="link">Create PDF document fillable form with HTML</a>
      operationId: createTemplateFromHtml
      parameters: []
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required:
              - html
              properties:
                html:
                  type: string
                  description: HTML template with field tags.
                  example: |
                    <p>Lorem Ipsum is simply dummy text of the
                    <text-field
                      name="Industry"
                      role="First Party"
                      required="false"
                      style="width: 80px; height: 16px; display: inline-block; margin-bottom: -4px">
                    </text-field>
                    and typesetting industry</p>
                html_header:
                  type: string
                  description: HTML template of the header to be displayed on every
                    page.
                html_footer:
                  type: string
                  description: HTML template of the footer to be displayed on every
                    page.
                name:
                  type: string
                  description: Template name. Random uuid will be assigned when not
                    specified.
                  example: Test Template
                size:
                  type: string
                  default: Letter
                  description: Page size. Letter 8.5 x 11 will be assigned when not
                    specified.
                  enum:
                  - Letter
                  - Legal
                  - Tabloid
                  - Ledger
                  - A0
                  - A1
                  - A2
                  - A3
                  - A4
                  - A5
                  - A6
                  example: A4
                external_id:
                  type: string
                  description: Your application-specific unique string key to identify
                    this template within your app. Existing template with specified
                    `external_id` will be updated with a new HTML.
                  example: 714d974e-83d8-11ee-b962-0242ac120002
                folder_name:
                  type: string
                  description: The folder's name to which the template should be created.
                documents:
                  type: array
                  description: The list of documents built from HTML. Can be used
                    to create a template with multiple documents. Leave `documents`
                    param empty when using a top-level `html` param for a template
                    with a single document.
                  items:
                    type: object
                    required:
                    - html
                    properties:
                      html:
                        type: string
                        description: HTML template with field tags.
                        example: |
                          <p>Lorem Ipsum is simply dummy text of the
                          <text-field
                            name="Industry"
                            role="First Party"
                            required="false"
                            style="width: 80px; height: 16px; display: inline-block; margin-bottom: -4px">
                          </text-field>
                          and typesetting industry</p>
                      name:
                        type: string
                        description: Document name. Random uuid will be assigned when
                          not specified.
                        example: Test Document
      responses:
        '200':
          description: OK
          content:
            application/json:
              schema:
                type: object
                required:
                - id
                - slug
                - name
                - preferences
                - schema
                - fields
                - submitters
                - author_id
                - archived_at
                - created_at
                - updated_at
                - source
                - external_id
                - folder_id
                - folder_name
                - author
                - documents
                properties:
                  id:
                    type: integer
                    description: Unique identifier of the document template.
                  slug:
                    type: string
                    description: Unique slug of the document template.
                  name:
                    type: string
                    description: Name of the template.
                  preferences:
                    type: object
                    description: Template preferences.
                  schema:
                    type: array
                    description: List of documents attached to the template.
                    items:
                      type: object
                      required:
                      - attachment_uuid
                      - name
                      properties:
                        attachment_uuid:
                          type: string
                          description: Unique indentifier of attached document to
                            the template.
                        name:
                          type: string
                          description: Name of the attached document to the template.
                  fields:
                    type: array
                    description: List of fields to be filled in the template.
                    items:
                      type: object
                      required:
                      - uuid
                      - submitter_uuid
                      - name
                      - type
                      - required
                      - areas
                      properties:
                        uuid:
                          type: string
                          description: Unique identifier of the field.
                        submitter_uuid:
                          type: string
                          description: Unique identifier of the submitter that filled
                            the field.
                        name:
                          type: string
                          description: Field name.
                        type:
                          type: string
                          description: Type of the field (e.g., text, signature, date,
                            initials).
                          enum:
                          - heading
                          - text
                          - signature
                          - initials
                          - date
                          - number
                          - image
                          - checkbox
                          - multiple
                          - file
                          - radio
                          - select
                          - cells
                          - stamp
                          - payment
                          - phone
                          - verification
                        required:
                          type: boolean
                          description: Indicates if the field is required.
                        preferences:
                          type: object
                          properties:
                            font_size:
                              type: integer
                              description: Font size of the field value in pixels.
                            font_type:
                              type: string
                              description: Font type of the field value.
                            font:
                              type: string
                              description: Font family of the field value.
                            color:
                              type: string
                              description: Font color of the field value.
                            align:
                              type: string
                              description: Horizontal alignment of the field text
                                value.
                            valign:
                              type: string
                              description: Vertical alignment of the field text value.
                            format:
                              type: string
                              description: The data format for different field types.
                            price:
                              type: number
                              description: Price value of the payment field. Only
                                for payment fields.
                            currency:
                              type: string
                              description: Currency value of the payment field. Only
                                for payment fields.
                            mask:
                              type: boolean
                              description: Indicates if the field is masked on the
                                document.
                        areas:
                          type: array
                          description: List of areas where the field is located in
                            the document.
                          items:
                            type: object
                            required:
                            - x
                            - "y"
                            - w
                            - h
                            - attachment_uuid
                            - page
                            properties:
                              x:
                                type: number
                                description: X coordinate of the area where the field
                                  is located in the document.
                              "y":
                                type: number
                                description: Y coordinate of the area where the field
                                  is located in the document.
                              w:
                                type: number
                                description: Width of the area where the field is
                                  located in the document.
                              h:
                                type: number
                                description: Height of the area where the field is
                                  located in the document.
                              attachment_uuid:
                                type: string
                                description: Unique identifier of the attached document
                                  where the field is located.
                              page:
                                type: integer
                                description: Page number of the attached document
                                  where the field is located.
                  submitters:
                    type: array
                    items:
                      type: object
                      required:
                      - name
                      - uuid
                      properties:
                        name:
                          type: string
                          description: Submitter name.
                        uuid:
                          type: string
                          description: Unique identifier of the submitter.
                  author_id:
                    type: integer
                    description: Unique identifier of the author of the template.
                  archived_at:
                    type: string
                    nullable: true
                    description: Date and time when the template was archived.
                  created_at:
                    type: string
                    description: Date and time when the template was created.
                  updated_at:
                    type: string
                    description: Date and time when the template was updated.
                  source:
                    type: string
                    description: Source of the template.
                    enum:
                    - native
                    - api
                    - embed
                  external_id:
                    type: string
                    nullable: true
                    description: Identifier of the template in the external system.
                  folder_id:
                    type: integer
                    description: Unique identifier of the folder where the template
                      is placed.
                  folder_name:
                    type: string
                    description: Folder name where the template is placed.
                  author:
                    type: object
                    required:
                    - id
                    - first_name
                    - last_name
                    - email
                    properties:
                      id:
                        type: integer
                        description: Unique identifier of the author.
                      first_name:
                        type: string
                        description: First name of the author.
                      last_name:
                        type: string
                        description: Last name of the author.
                      email:
                        type: string
                        description: Author email.
                  documents:
                    type: array
                    description: List of documents attached to the template.
                    items:
                      type: object
                      required:
                      - id
                      - uuid
                      - url
                      - preview_image_url
                      - filename
                      properties:
                        id:
                          type: integer
                          description: Unique identifier of the document.
                        uuid:
                          type: string
                          description: Unique identifier of the document.
                        url:
                          type: string
                          description: URL of the document.
                        preview_image_url:
                          type: string
                          description: Document preview image URL.
                        filename:
                          type: string
                          description: Document filename.
              example:
                id: 3
                slug: ZQpF222rFBv71q
                name: Demo Template
                schema:
                - name: Demo Template
                  attachment_uuid: '09a8bc73-a7a9-4fd9-8173-95752bdf0af5'
                fields:
                - name: Name
                  required: false
                  type: text
                  uuid: a06c49f6-4b20-4442-ac7f-c1040d2cf1ac
                  submitter_uuid: 93ba628c-5913-4456-a1e9-1a81ad7444b3
                  areas:
                  - page: 0
                    attachment_uuid: '09a8bc73-a7a9-4fd9-8173-95752bdf0af5'
                    x: 0.403158189124654
                    "y": 0.04211750189825361
                    w: 0.100684625476058
                    h: 0.01423690205011389
                submitters:
                - name: Submitter
                  uuid: 93ba628c-5913-4456-a1e9-1a81ad7444b3
                author_id: 1
                archived_at:
                created_at: '2023-12-14T15:50:21.799Z'
                updated_at: '2023-12-14T15:50:21.799Z'
                source: api
                folder_id: 1
                folder_name: Default
                external_id: f0b4714f-e44b-4993-905b-68b4451eef8c
                author:
                  id: 1
                  first_name: John
                  last_name: Doe
                  email: john.doe@example.com
                documents:
                - id: 3
                  uuid: '09a8bc73-a7a9-4fd9-8173-95752bdf0af5'
                  url: https://docuseal.com/file/hash/Test%20Template.pdf
  "/templates/docx":
    post:
      security:
      - AuthToken: []
      tags:
      - Templates
      summary: Create a template from Word DOCX
      description: 'The API endpoint provides the functionality to create a fillable
        document template for existing Microsoft Word document. Use <code>{{Field
        Name;role=Signer1;type=date}}</code> text tags to define fillable fields in
        the document. See <a href="https://www.docuseal.com/examples/fieldtags.docx"
        target="_blank" class="link font-bold" >https://www.docuseal.com/examples/fieldtags.docx</a>
        for more text tag formats. Or specify the exact pixel coordinates of the document
        fields using `fields` param.<br><b>Related Guides</b><br><a href="https://www.docuseal.com/guides/use-embedded-text-field-tags-in-the-pdf-to-create-a-fillable-form"
        class="link">Use embedded text field tags to create a fillable form</a>

        '
      operationId: createTemplateFromDocx
      parameters: []
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required:
              - documents
              properties:
                name:
                  type: string
                  description: Name of the template
                  example: Test DOCX
                external_id:
                  type: string
                  description: Your application-specific unique string key to identify
                    this template within your app. Existing template with specified
                    `external_id` will be updated with a new document.
                  example: unique-key
                folder_name:
                  type: string
                  description: The folder's name to which the template should be created.
                documents:
                  type: array
                  items:
                    type: object
                    required:
                    - name
                    - file
                    properties:
                      name:
                        type: string
                        description: Name of the document.
                      file:
                        type: string
                        example: base64
                        format: base64
                        description: Base64-encoded content of the DOCX file or downloadable
                          file URL
                      fields:
                        description: Fields are optional if you use {{...}} text tags
                          to define fields in the document.
                        type: array
                        items:
                          type: object
                          properties:
                            name:
                              type: string
                              description: Name of the field.
                            type:
                              type: string
                              description: Type of the field (e.g., text, signature,
                                date, initials).
                              enum:
                              - heading
                              - text
                              - signature
                              - initials
                              - date
                              - number
                              - image
                              - checkbox
                              - multiple
                              - file
                              - radio
                              - select
                              - cells
                              - stamp
                              - payment
                              - phone
                              - verification
                            role:
                              type: string
                              description: Role name of the signer.
                            required:
                              type: boolean
                              description: Indicates if the field is required.
                            title:
                              type: string
                              description: Field title displayed to the user instead
                                of the name, shown on the signing form. Supports Markdown.
                            description:
                              type: string
                              description: Field description displayed on the signing
                                form. Supports Markdown.
                            areas:
                              type: array
                              items:
                                type: object
                                properties:
                                  x:
                                    type: number
                                    description: X-coordinate of the field area.
                                  "y":
                                    type: number
                                    description: Y-coordinate of the field area.
                                  w:
                                    type: number
                                    description: Width of the field area.
                                  h:
                                    type: number
                                    description: Height of the field area.
                                  page:
                                    type: integer
                                    description: Page number of the field area. Starts
                                      from 1.
                                  option:
                                    type: string
                                    description: Option string value for 'radio' and
                                      'multiple' select field types.
                            options:
                              type: array
                              description: An array of option values for 'select'
                                field type.
                              items:
                                type: string
                              example:
                              - Option A
                              - Option B
                            preferences:
                              type: object
                              properties:
                                font_size:
                                  type: integer
                                  description: Font size of the field value in pixels.
                                  example: 12
                                font_type:
                                  type: string
                                  description: Font type of the field value.
                                  enum:
                                  - bold
                                  - italic
                                  - bold_italic
                                font:
                                  type: string
                                  description: Font family of the field value.
                                  enum:
                                  - Times
                                  - Helvetica
                                  - Courier
                                color:
                                  type: string
                                  description: Font color of the field value.
                                  enum:
                                  - black
                                  - white
                                  - blue
                                  default: black
                                align:
                                  type: string
                                  description: Horizontal alignment of the field text
                                    value.
                                  enum:
                                  - left
                                  - center
                                  - right
                                  default: left
                                valign:
                                  type: string
                                  description: Vertical alignment of the field text
                                    value.
                                  enum:
                                  - top
                                  - center
                                  - bottom
                                  default: center
                                format:
                                  type: string
                                  description: 'The data format for different field
                                    types.<br>- Date field: accepts formats such as
                                    DD/MM/YYYY (default: MM/DD/YYYY).<br>- Signature
                                    field: accepts drawn, typed, drawn_or_typed (default),
                                    or upload.<br>- Number field: accepts currency
                                    formats such as usd, eur, gbp.'
                                  example: DD/MM/YYYY
                                price:
                                  type: number
                                  description: Price value of the payment field. Only
                                    for payment fields.
                                  example: 99.99
                                currency:
                                  type: string
                                  description: Currency value of the payment field.
                                    Only for payment fields.
                                  enum:
                                  - USD
                                  - EUR
                                  - GBP
                                  - CAD
                                  - AUD
                                  default: USD
                                mask:
                                  description: Set `true` to make sensitive data masked
                                    on the document.
                                  oneOf:
                                  - type: integer
                                  - type: boolean
                                  default: false
      responses:
        '200':
          description: OK
          content:
            application/json:
              schema:
                type: object
                required:
                - id
                - slug
                - name
                - preferences
                - schema
                - fields
                - submitters
                - author_id
                - archived_at
                - created_at
                - updated_at
                - source
                - external_id
                - folder_id
                - folder_name
                - author
                - documents
                properties:
                  id:
                    type: integer
                    description: Unique identifier of the document template.
                  slug:
                    type: string
                    description: Unique slug of the document template.
                  name:
                    type: string
                    description: Name of the template.
                  preferences:
                    type: object
                    description: Template preferences.
                  schema:
                    type: array
                    description: List of documents attached to the template.
                    items:
                      type: object
                      required:
                      - attachment_uuid
                      - name
                      properties:
                        attachment_uuid:
                          type: string
                          description: Unique indentifier of attached document to
                            the template.
                        name:
                          type: string
                          description: Name of the attached document to the template.
                  fields:
                    type: array
                    description: List of fields to be filled in the template.
                    items:
                      type: object
                      required:
                      - uuid
                      - submitter_uuid
                      - name
                      - type
                      - required
                      - areas
                      properties:
                        uuid:
                          type: string
                          description: Unique identifier of the field.
                        submitter_uuid:
                          type: string
                          description: Unique identifier of the submitter that filled
                            the field.
                        name:
                          type: string
                          description: Field name.
                        type:
                          type: string
                          description: Type of the field (e.g., text, signature, date,
                            initials).
                          enum:
                          - heading
                          - text
                          - signature
                          - initials
                          - date
                          - number
                          - image
                          - checkbox
                          - multiple
                          - file
                          - radio
                          - select
                          - cells
                          - stamp
                          - payment
                          - phone
                          - verification
                        required:
                          type: boolean
                          description: Indicates if the field is required.
                        preferences:
                          type: object
                          properties:
                            font_size:
                              type: integer
                              description: Font size of the field value in pixels.
                            font_type:
                              type: string
                              description: Font type of the field value.
                            font:
                              type: string
                              description: Font family of the field value.
                            color:
                              type: string
                              description: Font color of the field value.
                            align:
                              type: string
                              description: Horizontal alignment of the field text
                                value.
                            valign:
                              type: string
                              description: Vertical alignment of the field text value.
                            format:
                              type: string
                              description: The data format for different field types.
                            price:
                              type: number
                              description: Price value of the payment field. Only
                                for payment fields.
                            currency:
                              type: string
                              description: Currency value of the payment field. Only
                                for payment fields.
                            mask:
                              type: boolean
                              description: Indicates if the field is masked on the
                                document.
                        areas:
                          type: array
                          description: List of areas where the field is located in
                            the document.
                          items:
                            type: object
                            required:
                            - x
                            - "y"
                            - w
                            - h
                            - attachment_uuid
                            - page
                            properties:
                              x:
                                type: number
                                description: X coordinate of the area where the field
                                  is located in the document.
                              "y":
                                type: number
                                description: Y coordinate of the area where the field
                                  is located in the document.
                              w:
                                type: number
                                description: Width of the area where the field is
                                  located in the document.
                              h:
                                type: number
                                description: Height of the area where the field is
                                  located in the document.
                              attachment_uuid:
                                type: string
                                description: Unique identifier of the attached document
                                  where the field is located.
                              page:
                                type: integer
                                description: Page number of the attached document
                                  where the field is located.
                  submitters:
                    type: array
                    items:
                      type: object
                      required:
                      - name
                      - uuid
                      properties:
                        name:
                          type: string
                          description: Submitter name.
                        uuid:
                          type: string
                          description: Unique identifier of the submitter.
                  author_id:
                    type: integer
                    description: Unique identifier of the author of the template.
                  archived_at:
                    type: string
                    nullable: true
                    description: Date and time when the template was archived.
                  created_at:
                    type: string
                    description: Date and time when the template was created.
                  updated_at:
                    type: string
                    description: Date and time when the template was updated.
                  source:
                    type: string
                    description: Source of the template.
                    enum:
                    - native
                    - api
                    - embed
                  external_id:
                    type: string
                    nullable: true
                    description: Identifier of the template in the external system.
                  folder_id:
                    type: integer
                    description: Unique identifier of the folder where the template
                      is placed.
                  folder_name:
                    type: string
                    description: Folder name where the template is placed.
                  author:
                    type: object
                    required:
                    - id
                    - first_name
                    - last_name
                    - email
                    properties:
                      id:
                        type: integer
                        description: Unique identifier of the author.
                      first_name:
                        type: string
                        description: First name of the author.
                      last_name:
                        type: string
                        description: Last name of the author.
                      email:
                        type: string
                        description: Author email.
                  documents:
                    type: array
                    description: List of documents attached to the template.
                    items:
                      type: object
                      required:
                      - id
                      - uuid
                      - url
                      - preview_image_url
                      - filename
                      properties:
                        id:
                          type: integer
                          description: Unique identifier of the document.
                        uuid:
                          type: string
                          description: Unique identifier of the document.
                        url:
                          type: string
                          description: URL of the document.
                        preview_image_url:
                          type: string
                          description: Document preview image URL.
                        filename:
                          type: string
                          description: Document filename.
              example:
                id: 5
                slug: s3ff992CoPjvaX
                name: Demo PDF
                schema:
                - name: Demo PDF
                  attachment_uuid: 48d2998f-266b-47e4-beb2-250ab7ccebdf
                fields:
                - name: Name
                  type: text
                  required: true
                  uuid: d0bf3c0c-1928-40c8-80f9-d9f3c6ad4eff
                  submitter_uuid: 0b0bff58-bc9a-475d-b4a9-2f3e5323faf7
                  areas:
                  - page: 1
                    attachment_uuid: 48d2998f-266b-47e4-beb2-250ab7ccebdf
                    x: 0.403158189124654
                    "y": 0.04211750189825361
                    w: 0.100684625476058
                    h: 0.01423690205011389
                submitters:
                - name: Submitter
                  uuid: 0b0bff58-bc9a-475d-b4a9-2f3e5323faf7
                author_id: 1
                archived_at:
                created_at: '2023-12-14T15:50:21.799Z'
                updated_at: '2023-12-14T15:50:21.799Z'
                source: api
                folder_id: 1
                folder_name: Default
                external_id: c248ffba-ef81-48b7-8e17-e3cecda1c1c5
                author:
                  id: 1
                  first_name: John
                  last_name: Doe
                  email: john.doe@example.com
                documents:
                - id: 7
                  uuid: 48d2998f-266b-47e4-beb2-250ab7ccebdf
                  url: https://docuseal.com/hash/DemoPDF.pdf
  "/templates/pdf":
    post:
      security:
      - AuthToken: []
      tags:
      - Templates
      summary: Create a template from existing PDF
      description: 'The API endpoint provides the functionality to create a fillable
        document template for existing PDF file. Use <code>{{Field Name;role=Signer1;type=date}}</code>
        text tags to define fillable fields in the document. See <a href="https://www.docuseal.com/examples/fieldtags.pdf"
        target="_blank" class="link font-bold">https://www.docuseal.com/examples/fieldtags.pdf</a>
        for more text tag formats. Or specify the exact pixel coordinates of the document
        fields using `fields` param.<br><b>Related Guides</b><br><a href="https://www.docuseal.com/guides/use-embedded-text-field-tags-in-the-pdf-to-create-a-fillable-form"
        class="link">Use embedded text field tags to create a fillable form</a>

        '
      operationId: createTemplateFromPdf
      parameters: []
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required:
              - documents
              properties:
                name:
                  type: string
                  description: Name of the template
                  example: Test PDF
                folder_name:
                  type: string
                  description: The folder's name to which the template should be created.
                external_id:
                  type: string
                  description: Your application-specific unique string key to identify
                    this template within your app. Existing template with specified
                    `external_id` will be updated with a new PDF.
                  example: unique-key
                documents:
                  type: array
                  items:
                    type: object
                    required:
                    - name
                    - file
                    properties:
                      name:
                        type: string
                        description: Name of the document.
                      file:
                        example: base64
                        type: string
                        format: base64
                        description: Base64-encoded content of the PDF file or downloadable
                          file URL.
                      fields:
                        type: array
                        description: Fields are optional if you use {{...}} text tags
                          to define fields in the document.
                        items:
                          type: object
                          properties:
                            name:
                              type: string
                              description: Name of the field.
                            type:
                              type: string
                              description: Type of the field (e.g., text, signature,
                                date, initials).
                              enum:
                              - heading
                              - text
                              - signature
                              - initials
                              - date
                              - number
                              - image
                              - checkbox
                              - multiple
                              - file
                              - radio
                              - select
                              - cells
                              - stamp
                              - payment
                              - phone
                              - verification
                            role:
                              type: string
                              description: Role name of the signer.
                            required:
                              type: boolean
                              description: Indicates if the field is required.
                            title:
                              type: string
                              description: Field title displayed to the user instead
                                of the name, shown on the signing form. Supports Markdown.
                            description:
                              type: string
                              description: Field description displayed on the signing
                                form. Supports Markdown.
                            areas:
                              type: array
                              items:
                                type: object
                                required:
                                - x
                                - "y"
                                - w
                                - h
                                - page
                                properties:
                                  x:
                                    type: number
                                    description: X-coordinate of the field area.
                                  "y":
                                    type: number
                                    description: Y-coordinate of the field area.
                                  w:
                                    type: number
                                    description: Width of the field area.
                                  h:
                                    type: number
                                    description: Height of the field area.
                                  page:
                                    type: integer
                                    description: Page number of the field area. Starts
                                      from 1.
                                    example: 1
                                  option:
                                    type: string
                                    description: Option string value for 'radio' and
                                      'multiple' select field types.
                            options:
                              type: array
                              description: An array of option values for 'select'
                                field type.
                              items:
                                type: string
                              example:
                              - Option A
                              - Option B
                            preferences:
                              type: object
                              properties:
                                font_size:
                                  type: integer
                                  description: Font size of the field value in pixels.
                                  example: 12
                                font_type:
                                  type: string
                                  description: Font type of the field value.
                                  enum:
                                  - bold
                                  - italic
                                  - bold_italic
                                font:
                                  type: string
                                  description: Font family of the field value.
                                  enum:
                                  - Times
                                  - Helvetica
                                  - Courier
                                color:
                                  type: string
                                  description: Font color of the field value.
                                  enum:
                                  - black
                                  - white
                                  - blue
                                  default: black
                                align:
                                  type: string
                                  description: Horizontal alignment of the field text
                                    value.
                                  enum:
                                  - left
                                  - center
                                  - right
                                  default: left
                                valign:
                                  type: string
                                  description: Vertical alignment of the field text
                                    value.
                                  enum:
                                  - top
                                  - center
                                  - bottom
                                  default: center
                                format:
                                  type: string
                                  description: 'The data format for different field
                                    types.<br>- Date field: accepts formats such as
                                    DD/MM/YYYY (default: MM/DD/YYYY).<br>- Signature
                                    field: accepts drawn, typed, drawn_or_typed (default),
                                    or upload.<br>- Number field: accepts currency
                                    formats such as usd, eur, gbp.'
                                  example: DD/MM/YYYY
                                price:
                                  type: number
                                  description: Price value of the payment field. Only
                                    for payment fields.
                                  example: 99.99
                                currency:
                                  type: string
                                  description: Currency value of the payment field.
                                    Only for payment fields.
                                  enum:
                                  - USD
                                  - EUR
                                  - GBP
                                  - CAD
                                  - AUD
                                  default: USD
                                mask:
                                  description: Set `true` to make sensitive data masked
                                    on the document.
                                  oneOf:
                                  - type: integer
                                  - type: boolean
                                  default: false
                      flatten:
                        type: boolean
                        description: Remove PDF form fields from the document.
                        default: false
                      remove_tags:
                        type: boolean
                        description: Pass `false` to disable the removal of {{text}}
                          tags from the PDF. This can be used along with transparent
                          text tags for faster and more robust PDF processing.
                        default: true
      responses:
        '200':
          description: OK
          content:
            application/json:
              schema:
                type: object
                required:
                - id
                - slug
                - name
                - preferences
                - schema
                - fields
                - submitters
                - author_id
                - archived_at
                - created_at
                - updated_at
                - source
                - external_id
                - folder_id
                - folder_name
                - author
                - documents
                properties:
                  id:
                    type: integer
                    description: Unique identifier of the document template.
                  slug:
                    type: string
                    description: Unique slug of the document template.
                  name:
                    type: string
                    description: Name of the template.
                  preferences:
                    type: object
                    description: Template preferences.
                  schema:
                    type: array
                    description: List of documents attached to the template.
                    items:
                      type: object
                      required:
                      - attachment_uuid
                      - name
                      properties:
                        attachment_uuid:
                          type: string
                          description: Unique indentifier of attached document to
                            the template.
                        name:
                          type: string
                          description: Name of the attached document to the template.
                  fields:
                    type: array
                    description: List of fields to be filled in the template.
                    items:
                      type: object
                      required:
                      - uuid
                      - submitter_uuid
                      - name
                      - type
                      - required
                      - areas
                      properties:
                        uuid:
                          type: string
                          description: Unique identifier of the field.
                        submitter_uuid:
                          type: string
                          description: Unique identifier of the submitter that filled
                            the field.
                        name:
                          type: string
                          description: Field name.
                        type:
                          type: string
                          description: Type of the field (e.g., text, signature, date,
                            initials).
                          enum:
                          - heading
                          - text
                          - signature
                          - initials
                          - date
                          - number
                          - image
                          - checkbox
                          - multiple
                          - file
                          - radio
                          - select
                          - cells
                          - stamp
                          - payment
                          - phone
                          - verification
                        required:
                          type: boolean
                          description: Indicates if the field is required.
                        preferences:
                          type: object
                          properties:
                            font_size:
                              type: integer
                              description: Font size of the field value in pixels.
                            font_type:
                              type: string
                              description: Font type of the field value.
                            font:
                              type: string
                              description: Font family of the field value.
                            color:
                              type: string
                              description: Font color of the field value.
                            align:
                              type: string
                              description: Horizontal alignment of the field text
                                value.
                            valign:
                              type: string
                              description: Vertical alignment of the field text value.
                            format:
                              type: string
                              description: The data format for different field types.
                            price:
                              type: number
                              description: Price value of the payment field. Only
                                for payment fields.
                            currency:
                              type: string
                              description: Currency value of the payment field. Only
                                for payment fields.
                            mask:
                              type: boolean
                              description: Indicates if the field is masked on the
                                document.
                        areas:
                          type: array
                          description: List of areas where the field is located in
                            the document.
                          items:
                            type: object
                            required:
                            - x
                            - "y"
                            - w
                            - h
                            - attachment_uuid
                            - page
                            properties:
                              x:
                                type: number
                                description: X coordinate of the area where the field
                                  is located in the document.
                              "y":
                                type: number
                                description: Y coordinate of the area where the field
                                  is located in the document.
                              w:
                                type: number
                                description: Width of the area where the field is
                                  located in the document.
                              h:
                                type: number
                                description: Height of the area where the field is
                                  located in the document.
                              attachment_uuid:
                                type: string
                                description: Unique identifier of the attached document
                                  where the field is located.
                              page:
                                type: integer
                                description: Page number of the attached document
                                  where the field is located.
                  submitters:
                    type: array
                    items:
                      type: object
                      required:
                      - name
                      - uuid
                      properties:
                        name:
                          type: string
                          description: Submitter name.
                        uuid:
                          type: string
                          description: Unique identifier of the submitter.
                  author_id:
                    type: integer
                    description: Unique identifier of the author of the template.
                  archived_at:
                    type: string
                    nullable: true
                    description: Date and time when the template was archived.
                  created_at:
                    type: string
                    description: Date and time when the template was created.
                  updated_at:
                    type: string
                    description: Date and time when the template was updated.
                  source:
                    type: string
                    description: Source of the template.
                    enum:
                    - native
                    - api
                    - embed
                  external_id:
                    type: string
                    nullable: true
                    description: Identifier of the template in the external system.
                  folder_id:
                    type: integer
                    description: Unique identifier of the folder where the template
                      is placed.
                  folder_name:
                    type: string
                    description: Folder name where the template is placed.
                  author:
                    type: object
                    required:
                    - id
                    - first_name
                    - last_name
                    - email
                    properties:
                      id:
                        type: integer
                        description: Unique identifier of the author.
                      first_name:
                        type: string
                        description: First name of the author.
                      last_name:
                        type: string
                        description: Last name of the author.
                      email:
                        type: string
                        description: Author email.
                  documents:
                    type: array
                    description: List of documents attached to the template.
                    items:
                      type: object
                      required:
                      - id
                      - uuid
                      - url
                      - preview_image_url
                      - filename
                      properties:
                        id:
                          type: integer
                          description: Unique identifier of the document.
                        uuid:
                          type: string
                          description: Unique identifier of the document.
                        url:
                          type: string
                          description: URL of the document.
                        preview_image_url:
                          type: string
                          description: Document preview image URL.
                        filename:
                          type: string
                          description: Document filename.
              example:
                id: 5
                slug: s3ff992CoPjvaX
                name: Demo PDF
                schema:
                - name: Demo PDF
                  attachment_uuid: 48d2998f-266b-47e4-beb2-250ab7ccebdf
                fields:
                - name: Name
                  type: text
                  required: true
                  uuid: d0bf3c0c-1928-40c8-80f9-d9f3c6ad4eff
                  submitter_uuid: 0b0bff58-bc9a-475d-b4a9-2f3e5323faf7
                  areas:
                  - page: 1
                    attachment_uuid: 48d2998f-266b-47e4-beb2-250ab7ccebdf
                    x: 0.403158189124654
                    "y": 0.04211750189825361
                    w: 0.100684625476058
                    h: 0.01423690205011389
                submitters:
                - name: Submitter
                  uuid: 0b0bff58-bc9a-475d-b4a9-2f3e5323faf7
                author_id: 1
                archived_at:
                created_at: '2023-12-14T15:50:21.799Z'
                updated_at: '2023-12-14T15:50:21.799Z'
                source: api
                folder_id: 1
                folder_name: Default
                external_id: c248ffba-ef81-48b7-8e17-e3cecda1c1c5
                author:
                  id: 1
                  first_name: John
                  last_name: Doe
                  email: john.doe@example.com
                documents:
                - id: 7
                  uuid: 48d2998f-266b-47e4-beb2-250ab7ccebdf
                  url: https://docuseal.com/file/hash/Demo%20PDF.pdf
  "/templates/merge":
    post:
      security:
      - AuthToken: []
      tags:
      - Templates
      summary: Merge templates
      description: The API endpoint allows you to merge multiple templates with documents
        and fields into a new combined template.
      operationId: mergeTemplate
      parameters: []
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required:
              - template_ids
              properties:
                template_ids:
                  type: array
                  description: An array of template ids to merge into a new template.
                  items:
                    type: integer
                  example:
                  - 321
                  - 432
                name:
                  type: string
                  description: Template name. Existing name with (Merged) suffix will
                    be used if not specified.
                  example: Merged Template
                folder_name:
                  type: string
                  description: The name of the folder in which the merged template
                    should be placed.
                external_id:
                  type: string
                  description: Your application-specific unique string key to identify
                    this template within your app.
                roles:
                  type: array
                  description: An array of submitter role names to be used in the
                    merged template.
                  items:
                    type: string
                  example:
                  - Agent
                  - Customer
      responses:
        '200':
          description: OK
          content:
            application/json:
              schema:
                type: object
                required:
                - id
                - slug
                - name
                - preferences
                - schema
                - fields
                - submitters
                - author_id
                - archived_at
                - created_at
                - updated_at
                - source
                - external_id
                - folder_id
                - folder_name
                - author
                - documents
                properties:
                  id:
                    type: integer
                    description: Unique identifier of the document template.
                  slug:
                    type: string
                    description: Unique slug of the document template.
                  name:
                    type: string
                    description: Name of the template.
                  preferences:
                    type: object
                    description: Template preferences.
                  schema:
                    type: array
                    description: List of documents attached to the template.
                    items:
                      type: object
                      required:
                      - attachment_uuid
                      - name
                      properties:
                        attachment_uuid:
                          type: string
                          description: Unique indentifier of attached document to
                            the template.
                        name:
                          type: string
                          description: Name of the attached document to the template.
                  fields:
                    type: array
                    description: List of fields to be filled in the template.
                    items:
                      type: object
                      required:
                      - uuid
                      - submitter_uuid
                      - name
                      - type
                      - required
                      - areas
                      properties:
                        uuid:
                          type: string
                          description: Unique identifier of the field.
                        submitter_uuid:
                          type: string
                          description: Unique identifier of the submitter that filled
                            the field.
                        name:
                          type: string
                          description: Field name.
                        type:
                          type: string
                          description: Type of the field (e.g., text, signature, date,
                            initials).
                          enum:
                          - heading
                          - text
                          - signature
                          - initials
                          - date
                          - number
                          - image
                          - checkbox
                          - multiple
                          - file
                          - radio
                          - select
                          - cells
                          - stamp
                          - payment
                          - phone
                          - verification
                        required:
                          type: boolean
                          description: Indicates if the field is required.
                        preferences:
                          type: object
                          properties:
                            font_size:
                              type: integer
                              description: Font size of the field value in pixels.
                            font_type:
                              type: string
                              description: Font type of the field value.
                            font:
                              type: string
                              description: Font family of the field value.
                            color:
                              type: string
                              description: Font color of the field value.
                            align:
                              type: string
                              description: Horizontal alignment of the field text
                                value.
                            valign:
                              type: string
                              description: Vertical alignment of the field text value.
                            format:
                              type: string
                              description: The data format for different field types.
                            price:
                              type: number
                              description: Price value of the payment field. Only
                                for payment fields.
                            currency:
                              type: string
                              description: Currency value of the payment field. Only
                                for payment fields.
                            mask:
                              type: boolean
                              description: Indicates if the field is masked on the
                                document.
                        areas:
                          type: array
                          description: List of areas where the field is located in
                            the document.
                          items:
                            type: object
                            required:
                            - x
                            - "y"
                            - w
                            - h
                            - attachment_uuid
                            - page
                            properties:
                              x:
                                type: number
                                description: X coordinate of the area where the field
                                  is located in the document.
                              "y":
                                type: number
                                description: Y coordinate of the area where the field
                                  is located in the document.
                              w:
                                type: number
                                description: Width of the area where the field is
                                  located in the document.
                              h:
                                type: number
                                description: Height of the area where the field is
                                  located in the document.
                              attachment_uuid:
                                type: string
                                description: Unique identifier of the attached document
                                  where the field is located.
                              page:
                                type: integer
                                description: Page number of the attached document
                                  where the field is located.
                  submitters:
                    type: array
                    items:
                      type: object
                      required:
                      - name
                      - uuid
                      properties:
                        name:
                          type: string
                          description: Submitter name.
                        uuid:
                          type: string
                          description: Unique identifier of the submitter.
                  author_id:
                    type: integer
                    description: Unique identifier of the author of the template.
                  archived_at:
                    type: string
                    nullable: true
                    description: Date and time when the template was archived.
                  created_at:
                    type: string
                    description: Date and time when the template was created.
                  updated_at:
                    type: string
                    description: Date and time when the template was updated.
                  source:
                    type: string
                    description: Source of the template.
                    enum:
                    - native
                    - api
                    - embed
                  external_id:
                    type: string
                    nullable: true
                    description: Identifier of the template in the external system.
                  folder_id:
                    type: integer
                    description: Unique identifier of the folder where the template
                      is placed.
                  folder_name:
                    type: string
                    description: Folder name where the template is placed.
                  author:
                    type: object
                    required:
                    - id
                    - first_name
                    - last_name
                    - email
                    properties:
                      id:
                        type: integer
                        description: Unique identifier of the author.
                      first_name:
                        type: string
                        description: First name of the author.
                      last_name:
                        type: string
                        description: Last name of the author.
                      email:
                        type: string
                        description: Author email.
                  documents:
                    type: array
                    description: List of documents attached to the template.
                    items:
                      type: object
                      required:
                      - id
                      - uuid
                      - url
                      - preview_image_url
                      - filename
                      properties:
                        id:
                          type: integer
                          description: Unique identifier of the document.
                        uuid:
                          type: string
                          description: Unique identifier of the document.
                        url:
                          type: string
                          description: URL of the document.
                        preview_image_url:
                          type: string
                          description: Document preview image URL.
                        filename:
                          type: string
                          description: Document filename.
              example:
                id: 6
                slug: Xc7opTqwwV9P7x
                name: Merged Template
                schema:
                - attachment_uuid: 68aa0716-61f0-4535-9ba4-6c00f835b080
                  name: sample-document
                fields:
                - uuid: 93c7065b-1b19-4551-b67b-9946bf1c11c9
                  submitter_uuid: ad128012-756d-4d17-b728-6f6b1d482bb5
                  name: Name
                  type: text
                  required: true
                  areas:
                  - page: 0
                    attachment_uuid: '09a8bc73-a7a9-4fd9-8173-95752bdf0af5'
                    x: 0.403158189124654
                    "y": 0.04211750189825361
                    w: 0.100684625476058
                    h: 0.01423690205011389
                submitters:
                - name: First Party
                  uuid: ad128012-756d-4d17-b728-6f6b1d482bb5
                author_id: 1
                archived_at:
                created_at: '2023-12-14T15:50:21.799Z'
                updated_at: '2023-12-14T15:50:21.799Z'
                source: api
                folder_id: 2
                folder_name: Default
                external_id:
                author:
                  id: 1
                  first_name: John
                  last_name: Doe
                  email: john.doe@example.com
                documents:
                - id: 9
                  uuid: ded62277-9705-4fac-b5dc-58325d4102eb
                  url: https:/docuseal.com/file/hash/sample-document.pdf
                  filename: sample-document.pdf