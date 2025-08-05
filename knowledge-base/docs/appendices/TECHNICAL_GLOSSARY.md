# Technical Glossary

**Version:** 1.0  
**Last Updated:** January 2025  
**Audience:** Developers, Technical Staff, Stakeholders

---

## 📋 Overview

This glossary provides definitions for technical terms, acronyms, and concepts used throughout the MSC Wound Care Portal platform and documentation.

## 🔤 A

### API (Application Programming Interface)
A set of protocols and tools for building software applications. In our context, refers to REST APIs that allow external systems to interact with the platform.

### ASP.NET Core
Microsoft's cross-platform framework used for building web applications and APIs. Not directly used in our Laravel-based system but relevant for Azure integrations.

### Azure
Microsoft's cloud computing platform providing services like Azure Health Data Services, App Services, and storage solutions used by our platform.

### Azure FHIR Service
Microsoft's managed FHIR service that provides secure storage and access to healthcare data following FHIR R4 standards.

### Azure Health Data Services
Microsoft's healthcare-specific cloud services including FHIR, DICOM, and MedTech services.

## 🔤 B

### Background Jobs
Tasks that run asynchronously separate from web requests, typically handled by Laravel's queue system using Redis or database drivers.

### Blade
Laravel's templating engine, though our application primarily uses Inertia.js with React components instead.

### Bootstrap
CSS framework for responsive web design. Our application uses Tailwind CSS instead.

## 🔤 C

### Circuit Breaker
A design pattern that prevents cascading failures by monitoring external service calls and "opening" when failure thresholds are reached.

### CORS (Cross-Origin Resource Sharing)
Web security feature that allows or restricts web pages from accessing resources from other domains.

### CPT (Current Procedural Terminology)
Medical code set used to describe medical, surgical, and diagnostic procedures and services.

### CRUD (Create, Read, Update, Delete)
The four basic operations that can be performed on data in most applications.

### CSS (Cascading Style Sheets)
Stylesheet language used for styling web pages. Our application uses Tailwind CSS framework.

## 🔤 D

### DTO (Data Transfer Object)
Design pattern used to transfer data between different layers or components of an application.

### DocuSeal
Third-party service used for document generation, e-signatures, and form management within our platform.

### Doctrine
PHP ORM (Object-Relational Mapping) library. Our application uses Laravel's Eloquent ORM instead.

## 🔤 E

### Eloquent
Laravel's built-in ORM (Object-Relational Mapping) system for database interactions.

### EHR (Electronic Health Record)
Digital version of patient health information, which our platform integrates with via FHIR.

### Episode
A specific instance of patient-manufacturer document generation workflow in our system.

## 🔤 F

### FHIR (Fast Healthcare Interoperability Resources)
HL7 standard for healthcare data exchange. Our platform implements FHIR R4 for patient data management.

### Facade
Laravel design pattern that provides a static interface to classes bound in the service container.

## 🔤 G

### GraphQL
Query language for APIs. Currently not implemented but considered for future development.

### Git
Version control system used for managing code changes and collaboration.

### Guzzle
PHP HTTP client library used for making external API calls in our Laravel application.

## 🔤 H

### HCPCS (Healthcare Common Procedure Coding System)
Medical coding system used for billing and insurance purposes, including CPT codes.

### HTTP (Hypertext Transfer Protocol)
Protocol used for web communication. Our APIs use HTTP/HTTPS protocols.

### HTTPS (HTTP Secure)
Encrypted version of HTTP used for secure web communication.

## 🔤 I

### ICD-10
International Classification of Diseases, 10th revision, used for medical diagnosis coding.

### Inertia.js
Framework that allows building single-page applications using classic server-side routing and controllers.

### IVR (Interactive Voice Response)
In our context, refers to interactive forms that collect patient and clinical information.

## 🔤 J

### JavaScript
Programming language used for frontend development in our React-based interface.

### JSON (JavaScript Object Notation)
Data interchange format used for API responses and configuration in our application.

### JWT (JSON Web Token)
Token format for secure information transmission. Our application uses Laravel Sanctum instead.

## 🔤 L

### Laravel
PHP web application framework that serves as the backend foundation of our platform.

### LCD (Local Coverage Determination)
Medicare contractor-specific coverage policies that determine what services are covered.

### LOINC (Logical Observation Identifiers Names and Codes)
Standard for laboratory and clinical observations used in FHIR resources.

## 🔤 M

### MAC (Medicare Administrative Contractor)
Organizations that process Medicare claims for specific geographic regions.

### Middleware
Software that provides common services to applications beyond what the operating system provides.

### Migration
Database schema change script in Laravel that allows version control of database structure.

### MySQL
Relational database management system used as our primary data store.

## 🔤 N

### NCD (National Coverage Determination)
Medicare policies that determine coverage nationwide for specific services.

### Node.js
JavaScript runtime used for frontend build tools and development utilities.

### NPM (Node Package Manager)
Package manager for JavaScript dependencies used in our frontend build process.

### NPI (National Provider Identifier)
Unique identifier for healthcare providers in the United States.

## 🔤 O

### ORM (Object-Relational Mapping)
Programming technique for converting data between incompatible systems. Laravel uses Eloquent ORM.

### OAuth
Authorization framework that enables third-party applications to access user accounts.

## 🔤 P

### PHI (Protected Health Information)
Individual health information that is protected under HIPAA regulations.

### Polymorphic Relationship
Database relationship where a model can belong to more than one other model on a single association.

### PSR (PHP Standards Recommendations)
PHP coding standards that our application follows for consistency.

## 🔤 Q

### Queue
System for handling background jobs asynchronously, implemented using Laravel's queue system.

### Quick Request
Our platform's core 90-second ordering workflow feature.

## 🔤 R

### RBAC (Role-Based Access Control)
Security model that restricts system access based on user roles and permissions.

### React
JavaScript library for building user interfaces, used for our frontend components.

### Redis
In-memory data structure store used for caching, sessions, and queue management.

### REST (Representational State Transfer)
Architectural style for designing networked applications, used for our API design.

## 🔤 S

### Sanctum
Laravel's authentication system for SPAs and simple APIs, used in our application.

### SPA (Single Page Application)
Web application that loads a single HTML page and dynamically updates content.

### SQL (Structured Query Language)
Programming language for managing relational databases.

### SSL/TLS (Secure Sockets Layer/Transport Layer Security)
Cryptographic protocols for secure communication over networks.

## 🔤 T

### Tailwind CSS
Utility-first CSS framework used for styling our frontend components.

### TypeScript
Typed superset of JavaScript used for type safety in our frontend development.

## 🔤 U

### UUID (Universally Unique Identifier)
128-bit identifier used for unique identification across systems.

### URI (Uniform Resource Identifier)
String that identifies a resource, commonly used in web applications.

## 🔤 V

### Validation
Process of ensuring that data meets specific criteria before processing.

### Vite
Frontend build tool used for fast development and production builds.

### Vue.js
JavaScript framework. Our application uses React instead, but Vue knowledge may be relevant.

## 🔤 W

### Webhook
HTTP callback that occurs when something happens, used for real-time integrations.

### WebSocket
Communication protocol for real-time bidirectional communication between client and server.

## 🔤 X

### XML (eXtensible Markup Language)
Markup language used for data exchange, relevant for some healthcare integrations.

### XSS (Cross-Site Scripting)
Security vulnerability that our application protects against through proper input validation.

## 🔤 Y

### YAML (YAML Ain't Markup Language)
Human-readable data serialization standard used for configuration files.

## 🔤 Z

### ZIP Code
Postal code system used for geographic-based Medicare MAC determination.

---

## 📊 Platform-Specific Terms

### Commission Engine
System component that calculates sales representative commissions based on orders and territories.

### Clinical Opportunity Engine
AI-powered system that identifies potential clinical interventions and recommendations.

### Episode Management
System for tracking patient-manufacturer document generation workflows.

### Field Mapping Service
Component that transforms data between different formats (e.g., FHIR to IVR forms).

### MAC Validation
Process of determining Medicare coverage and documentation requirements based on geographic location.

### Quick Request Orchestrator
Service that coordinates the 90-second ordering workflow across multiple systems.

### Unified Field Mapping
Consolidated service for handling data transformation across all manufacturers and forms.

---

## 🔗 Related Terms by Category

### Frontend Development
- React, Inertia.js, TypeScript, Tailwind CSS, Vite, npm, Node.js

### Backend Development
- Laravel, PHP, Eloquent, MySQL, Redis, Sanctum, Queue, Migration

### Healthcare Standards
- FHIR, HL7, ICD-10, CPT, HCPCS, LOINC, NPI, PHI, HIPAA

### Cloud & Infrastructure
- Azure, Docker, nginx, SSL/TLS, CORS, CDN

### Integration & APIs
- REST, JSON, XML, Webhook, OAuth, JWT, Guzzle

### Database & Storage
- MySQL, Redis, Migration, ORM, UUID, SQL

### Security & Compliance
- RBAC, PHI, HIPAA, SSL/TLS, XSS, CORS, Authentication

---

**Maintenance Note:** This glossary should be updated whenever new technical terms are introduced to the platform or when existing definitions need clarification.

**Related Documentation:**
- [Healthcare Glossary](./HEALTHCARE_GLOSSARY.md)
- [Business Glossary](./BUSINESS_GLOSSARY.md)
- [Development Setup](../development/DEVELOPMENT_SETUP.md)
