# System & Administrative Templates

This document contains all email templates related to system notifications, administrative alerts, and operational communications in the MSC Wound Care Portal.

## Template Overview

### Available System & Administrative Templates

1. **System Maintenance Notice** - Planned maintenance announcements
2. **Data Backup Complete** - Backup completion notifications
3. **Security Breach Alert** - Critical security incident notifications
4. **Performance Alert** - System performance warnings
5. **License Expiration Warning** - Software license renewal reminders
6. **Compliance Audit Notice** - Regulatory compliance notifications

## 1. System Maintenance Notice Template

**File**: `resources/views/emails/system-maintenance.blade.php`

```blade
@extends('emails.layout')

@section('title', 'Scheduled Maintenance - MSC Wound Care Portal')
@section('aria-label', 'System maintenance announcement')

@section('content')
    <h1 style="color: var(--msc-blue);">🔧 Scheduled System Maintenance</h1>
    
    <p>Dear MSC Wound Care Portal Users,</p>
    
    <p>We will be performing scheduled maintenance to improve system performance and add new features. During this time, the portal will be temporarily unavailable.</p>
    
    <div class="info-box warning">
        <h2 style="margin: 0 0 15px 0; font-size: 18px;">⏰ Maintenance Schedule</h2>
        <table style="width: 100%; margin: 0;">
            <tr>
                <td style="width: 30%; font-weight: 600; color: var(--msc-blue); padding: 8px 0;">Start Time:</td>
                <td style="padding: 8px 0;">{{ $maintenanceStart->format('F j, Y g:i A T') }}</td>
            </tr>
            <tr>
                <td style="font-weight: 600; color: var(--msc-blue); padding: 8px 0;">End Time:</td>
                <td style="padding: 8px 0;">{{ $maintenanceEnd->format('F j, Y g:i A T') }}</td>
            </tr>
            <tr>
                <td style="font-weight: 600; color: var(--msc-blue); padding: 8px 0;">Duration:</td>
                <td style="padding: 8px 0;">{{ $maintenanceStart->diffForHumans($maintenanceEnd, true) }}</td>
            </tr>
            <tr>
                <td style="font-weight: 600; color: var(--msc-blue); padding: 8px 0;">Timezone:</td>
                <td style="padding: 8px 0;">{{ $maintenanceStart->getTimezone()->getName() }}</td>
            </tr>
            <tr>
                <td style="font-weight: 600; color: var(--msc-blue); padding: 8px 0;">Type:</td>
                <td style="padding: 8px 0;">{{ $maintenanceType ?? 'Routine System Updates' }}</td>
            </tr>
        </table>
    </div>
    
    <div style="margin: 30px 0;">
        <h2>What to Expect</h2>
        <div class="info-box">
            <ul style="margin: 0; padding-left: 20px; font-size: 14px;">
                <li><strong>Portal Access:</strong> Complete unavailability during maintenance window</li>
                <li><strong>Mobile App:</strong> Limited functionality; sync will resume after maintenance</li>
                <li><strong>API Services:</strong> All integrations will be temporarily suspended</li>
                <li><strong>Email Notifications:</strong> May be delayed but will be delivered after completion</li>
                <li><strong>Data Safety:</strong> All data is securely backed up and protected</li>
            </ul>
        </div>
    </div>
    
    @if(isset($impactedFeatures) && count($impactedFeatures) > 0)
        <div style="margin: 30px 0;">
            <h2>Affected Features</h2>
            <div class="info-box warning">
                <ul style="margin: 0; padding-left: 20px; font-size: 14px;">
                    @foreach($impactedFeatures as $feature)
                        <li>{{ $feature }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif
    
    @if(isset($newFeatures) && count($newFeatures) > 0)
        <div style="margin: 30px 0;">
            <h2>🎉 New Features After Maintenance</h2>
            <div class="info-box success">
                <ul style="margin: 0; padding-left: 20px; font-size: 14px;">
                    @foreach($newFeatures as $feature)
                        <li>{{ $feature }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif
    
    <div style="margin: 30px 0;">
        <h2>📋 Recommended Actions</h2>
        <div class="info-box">
            <ol style="margin: 0; padding-left: 20px; font-size: 14px;">
                <li><strong>Complete Pending Orders:</strong> Submit any urgent orders before maintenance begins</li>
                <li><strong>Save Your Work:</strong> Ensure all data is saved and work is completed</li>
                <li><strong>Download Reports:</strong> Export any needed reports before the maintenance window</li>
                <li><strong>Plan Accordingly:</strong> Schedule critical activities outside the maintenance window</li>
                <li><strong>Update Bookmarks:</strong> Clear browser cache after maintenance is complete</li>
            </ol>
        </div>
    </div>
    
    <div style="text-align: center; margin: 30px 0;">
        <a href="{{ $statusPageLink ?? 'https://status.mscwoundcare.com' }}" class="button button-secondary">
            View Live Status Updates
        </a>
        <p style="margin: 15px 0 0 0; font-size: 14px;">
            Get real-time updates on maintenance progress.
        </p>
    </div>
    
    @if(isset($emergencyContact))
        <div class="info-box error">
            <h3 style="margin: 0 0 10px 0; font-size: 16px;">🚨 Emergency Situations</h3>
            <p style="margin: 0; font-size: 14px;">
                For critical, time-sensitive medical situations during maintenance:
            </p>
            <ul style="margin: 10px 0 0 0; padding-left: 20px; font-size: 14px;">
                <li>Contact: {{ $emergencyContact['phone'] ?? '(555) 123-EMRG' }}</li>
                <li>Email: {{ $emergencyContact['email'] ?? 'emergency@mscwoundcare.com' }}</li>
                <li>Available 24/7 for urgent medical supply needs</li>
            </ul>
        </div>
    @endif
    
    <div style="background-color: var(--msc-light-blue); padding: 20px; border-radius: 6px; margin-top: 30px;">
        <p style="margin: 0; font-size: 14px; text-align: center;">
            <strong>Questions about this maintenance?</strong><br>
            Contact our support team at <a href="mailto:support@mscwoundcare.com" style="color: var(--msc-blue);">support@mscwoundcare.com</a><br>
            or call (555) 123-4567 during business hours.
        </p>
    </div>
    
    <p style="margin-top: 20px; font-size: 12px; color: var(--text-muted); text-align: center;">
        We appreciate your patience and understanding as we work to improve your experience.
    </p>
@endsection
```

## 2. Data Backup Complete Template

**File**: `resources/views/emails/backup-complete.blade.php`

```blade
@extends('emails.layout')

@section('title', 'Data Backup Complete - MSC Wound Care Portal')
@section('aria-label', 'Data backup completion notification')

@section('content')
    <h1 style="color: #10b981;">💾 Data Backup Complete</h1>
    
    <p>Dear MSC System Administrator,</p>
    
    <p>The scheduled data backup has been successfully completed. All system data has been securely backed up and verified.</p>
    
    <div class="info-box success">
        <h2 style="margin: 0 0 15px 0; font-size: 18px;">Backup Summary</h2>
        <table style="width: 100%; margin: 0;">
            <tr>
                <td style="width: 30%; font-weight: 600; color: var(--msc-blue); padding: 8px 0;">Backup ID:</td>
                <td style="padding: 8px 0; font-family: monospace;">{{ $backupId }}</td>
            </tr>
            <tr>
                <td style="font-weight: 600; color: var(--msc-blue); padding: 8px 0;">Started:</td>
                <td style="padding: 8px 0;">{{ $backupStarted->format('F j, Y g:i:s A T') }}</td>
            </tr>
            <tr>
                <td style="font-weight: 600; color: var(--msc-blue); padding: 8px 0;">Completed:</td>
                <td style="padding: 8px 0;">{{ $backupCompleted->format('F j, Y g:i:s A T') }}</td>
            </tr>
            <tr>
                <td style="font-weight: 600; color: var(--msc-blue); padding: 8px 0;">Duration:</td>
                <td style="padding: 8px 0;">{{ $backupStarted->diffForHumans($backupCompleted, true) }}</td>
            </tr>
            <tr>
                <td style="font-weight: 600; color: var(--msc-blue); padding: 8px 0;">Status:</td>
                <td style="padding: 8px 0;"><span class="badge badge-approved">SUCCESS</span></td>
            </tr>
        </table>
    </div>
    
    <div style="margin: 30px 0;">
        <h2>Backup Details</h2>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Component</th>
                    <th>Size</th>
                    <th>Files/Records</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($backupComponents as $component)
                <tr>
                    <td style="font-weight: 600;">{{ $component['name'] }}</td>
                    <td>{{ $component['size'] }}</td>
                    <td>{{ number_format($component['count']) }}</td>
                    <td>
                        @if($component['status'] === 'success')
                            <span class="badge badge-approved">✓</span>
                        @else
                            <span class="badge badge-denied">✗</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr style="background-color: var(--bg-light); font-weight: 600;">
                    <td>Total Backup Size</td>
                    <td>{{ $totalBackupSize }}</td>
                    <td>{{ number_format($totalRecords) }} records</td>
                    <td><span class="badge badge-approved">VERIFIED</span></td>
                </tr>
            </tfoot>
        </table>
    </div>
    
    <div style="margin: 30px 0;">
        <h2>🔒 Security & Verification</h2>
        <div class="info-box">
            <table style="width: 100%; margin: 0; font-size: 14px;">
                <tr>
                    <td style="width: 30%; font-weight: 600; color: var(--msc-blue); padding: 4px 0;">Encryption:</td>
                    <td style="padding: 4px 0;">AES-256 with rotating keys</td>
                </tr>
                <tr>
                    <td style="font-weight: 600; color: var(--msc-blue); padding: 4px 0;">Checksum:</td>
                    <td style="padding: 4px 0; font-family: monospace; font-size: 12px;">{{ $checksumHash }}</td>
                </tr>
                <tr>
                    <td style="font-weight: 600; color: var(--msc-blue); padding: 4px 0;">Integrity Check:</td>
                    <td style="padding: 4px 0; color: #10b981; font-weight: 600;">PASSED</td>
                </tr>
                <tr>
                    <td style="font-weight: 600; color: var(--msc-blue); padding: 4px 0;">Storage Location:</td>
                    <td style="padding: 4px 0;">{{ $storageLocation ?? 'Secure Cloud Storage (Multi-Region)' }}</td>
                </tr>
                <tr>
                    <td style="font-weight: 600; color: var(--msc-blue); padding: 4px 0;">Retention:</td>
                    <td style="padding: 4px 0;">{{ $retentionPeriod ?? '7 years (HIPAA compliant)' }}</td>
                </tr>
            </table>
        </div>
    </div>
    
    @if(isset($performanceMetrics))
        <div style="margin: 30px 0;">
            <h2>📊 Performance Metrics</h2>
            <div class="info-box">
                <table style="width: 100%; margin: 0; font-size: 14px;">
                    <tr>
                        <td style="width: 30%; font-weight: 600; color: var(--msc-blue); padding: 4px 0;">Throughput:</td>
                        <td style="padding: 4px 0;">{{ $performanceMetrics['throughput'] ?? 'N/A' }}</td>
                    </tr>
                    <tr>
                        <td style="font-weight: 600; color: var(--msc-blue); padding: 4px 0;">Compression Ratio:</td>
                        <td style="padding: 4px 0;">{{ $performanceMetrics['compression'] ?? 'N/A' }}</td>
                    </tr>
                    <tr>
                        <td style="font-weight: 600; color: var(--msc-blue); padding: 4px 0;">Deduplication:</td>
                        <td style="padding: 4px 0;">{{ $performanceMetrics['deduplication'] ?? 'N/A' }}</td>
                    </tr>
                    <tr>
                        <td style="font-weight: 600; color: var(--msc-blue); padding: 4px 0;">CPU Usage:</td>
                        <td style="padding: 4px 0;">{{ $performanceMetrics['cpu_usage'] ?? 'N/A' }}</td>
                    </tr>
                </table>
            </div>
        </div>
    @endif
    
    <div style="text-align: center; margin: 30px 0;">
        <a href="{{ $backupDetailsLink }}" class="button button-primary">
            View Detailed Backup Report
        </a>
    </div>
    
    @if(isset($nextBackupScheduled))
        <div class="info-box">
            <h3 style="margin: 0 0 10px 0; font-size: 16px;">📅 Next Scheduled Backup</h3>
            <p style="margin: 0; font-size: 14px;">
                <strong>Date:</strong> {{ $nextBackupScheduled->format('F j, Y g:i A T') }}<br>
                <strong>Type:</strong> {{ $nextBackupType ?? 'Incremental' }}<br>
                <strong>Estimated Duration:</strong> {{ $estimatedDuration ?? '30-45 minutes' }}
            </p>
        </div>
    @endif
    
    @if(isset($issues) && count($issues) > 0)
        <div class="info-box warning">
            <h3 style="margin: 0 0 10px 0; font-size: 16px;">⚠️ Issues Detected</h3>
            <ul style="margin: 0; padding-left: 20px; font-size: 14px;">
                @foreach($issues as $issue)
                    <li>{{ $issue }}</li>
                @endforeach
            </ul>
            <p style="margin: 10px 0 0 0; font-size: 14px;">
                <strong>Action Required:</strong> Please review and address these issues before the next backup.
            </p>
        </div>
    @endif
    
    <div style="background-color: var(--bg-light); padding: 20px; border-radius: 6px; margin-top: 30px;">
        <p style="margin: 0; font-size: 14px; text-align: center;">
            <strong>Backup System Health:</strong> All systems operational<br>
            For backup-related issues: <a href="mailto:backups@mscwoundcare.com" style="color: var(--msc-blue);">backups@mscwoundcare.com</a>
        </p>
    </div>
@endsection
```

## 3. Security Breach Alert Template

**File**: `resources/views/emails/security-breach.blade.php`

```blade
@extends('emails.layout')

@section('title', 'URGENT: Security Incident - MSC Wound Care Portal')
@section('aria-label', 'Critical security breach notification')

@section('content')
    <h1 style="color: var(--msc-red);">🚨 URGENT: Security Incident Alert</h1>
    
    <p>Dear MSC Administrator,</p>
    
    <p><strong>This is an urgent security notification.</strong> A potential security incident has been detected and is being investigated.</p>
    
    <div class="info-box error">
        <h2 style="margin: 0 0 15px 0; font-size: 18px;">🔴 Incident Details</h2>
        <table style="width: 100%; margin: 0;">
            <tr>
                <td style="width: 30%; font-weight: 600; color: var(--msc-red); padding: 8px 0;">Incident ID:</td>
                <td style="padding: 8px 0; font-family: monospace;">{{ $incidentId }}</td>
            </tr>
            <tr>
                <td style="font-weight: 600; color: var(--msc-red); padding: 8px 0;">Detected:</td>
                <td style="padding: 8px 0;">{{ $detectedAt->format('F j, Y g:i:s A T') }}</td>
            </tr>
            <tr>
                <td style="font-weight: 600; color: var(--msc-red); padding: 8px 0;">Severity:</td>
                <td style="padding: 8px 0;">
                    <span style="background-color: var(--msc-red); color: white; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600;">
                        {{ strtoupper($severity) }}
                    </span>
                </td>
            </tr>
            <tr>
                <td style="font-weight: 600; color: var(--msc-red); padding: 8px 0;">Type:</td>
                <td style="padding: 8px 0;">{{ $incidentType }}</td>
            </tr>
            <tr>
                <td style="font-weight: 600; color: var(--msc-red); padding: 8px 0;">Source IP:</td>
                <td style="padding: 8px 0; font-family: monospace;">{{ $sourceIP ?? 'Multiple/Unknown' }}</td>
            </tr>
            <tr>
                <td style="font-weight: 600; color: var(--msc-red); padding: 8px 0;">Status:</td>
                <td style="padding: 8px 0;">{{ $currentStatus ?? 'Under Investigation' }}</td>
            </tr>
        </table>
    </div>
    
    <div style="margin: 30px 0;">
        <h2>🛡️ Immediate Actions Taken</h2>
        <div class="info-box success">
            <ul style="margin: 0; padding-left: 20px; font-size: 14px;">
                @foreach($actionsTaken as $action)
                    <li>{{ $action }}</li>
                @endforeach
            </ul>
        </div>
    </div>
    
    @if(isset($affectedSystems) && count($affectedSystems) > 0)
        <div style="margin: 30px 0;">
            <h2>⚠️ Potentially Affected Systems</h2>
            <div class="info-box warning">
                <ul style="margin: 0; padding-left: 20px; font-size: 14px;">
                    @foreach($affectedSystems as $system)
                        <li>{{ $system }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif
    
    @if(isset($compromisedData))
        <div style="margin: 30px 0;">
            <h2>📊 Data Impact Assessment</h2>
            <div class="info-box error">
                <table style="width: 100%; margin: 0; font-size: 14px;">
                    <tr>
                        <td style="width: 30%; font-weight: 600; color: var(--msc-red); padding: 4px 0;">User Accounts:</td>
                        <td style="padding: 4px 0;">{{ $compromisedData['users'] ?? 'Under Investigation' }}</td>
                    </tr>
                    <tr>
                        <td style="font-weight: 600; color: var(--msc-red); padding: 4px 0;">Orders:</td>
                        <td style="padding: 4px 0;">{{ $compromisedData['orders'] ?? 'Under Investigation' }}</td>
                    </tr>
                    <tr>
                        <td style="font-weight: 600; color: var(--msc-red); padding: 4px 0;">PHI Records:</td>
                        <td style="padding: 4px 0;">{{ $compromisedData['phi'] ?? 'Under Investigation' }}</td>
                    </tr>
                    <tr>
                        <td style="font-weight: 600; color: var(--msc-red); padding: 4px 0;">Financial Data:</td>
                        <td style="padding: 4px 0;">{{ $compromisedData['financial'] ?? 'Under Investigation' }}</td>
                    </tr>
                </table>
            </div>
        </div>
    @endif
    
    <div style="margin: 30px 0;">
        <h2>🚀 Required Immediate Actions</h2>
        <div class="info-box error">
            <ol style="margin: 0; padding-left: 20px; font-size: 14px; font-weight: 600;">
                <li>Alert security team and incident response personnel</li>
                <li>Preserve all logs and evidence related to this incident</li>
                <li>Monitor for additional suspicious activity</li>
                <li>Prepare for potential system lockdown if escalation occurs</li>
                <li>Notify legal and compliance teams as appropriate</li>
                @if($severity === 'critical')
                    <li style="color: var(--msc-red);">CRITICAL: Consider activating disaster recovery procedures</li>
                @endif
            </ol>
        </div>
    </div>
    
    <div style="text-align: center; margin: 30px 0;">
        <a href="{{ $incidentDashboardLink }}" class="button button-primary" style="background-color: var(--msc-red); border-color: var(--msc-red);">
            Access Incident Dashboard
        </a>
        <p style="margin: 15px 0 0 0; font-size: 14px;">
            Real-time incident tracking and response coordination.
        </p>
    </div>
    
    @if(isset($forensicData))
        <div style="margin: 30px 0;">
            <h2>🔍 Forensic Information</h2>
            <div class="info-box">
                <table style="width: 100%; margin: 0; font-size: 12px; font-family: monospace;">
                    <tr>
                        <td style="width: 30%; font-weight: 600; padding: 4px 0;">Attack Vector:</td>
                        <td style="padding: 4px 0;">{{ $forensicData['vector'] ?? 'Under Analysis' }}</td>
                    </tr>
                    <tr>
                        <td style="font-weight: 600; padding: 4px 0;">First Seen:</td>
                        <td style="padding: 4px 0;">{{ $forensicData['first_seen'] ?? 'Unknown' }}</td>
                    </tr>
                    <tr>
                        <td style="font-weight: 600; padding: 4px 0;">Last Activity:</td>
                        <td style="padding: 4px 0;">{{ $forensicData['last_activity'] ?? 'Ongoing' }}</td>
                    </tr>
                    <tr>
                        <td style="font-weight: 600; padding: 4px 0;">Indicators:</td>
                        <td style="padding: 4px 0;">{{ $forensicData['indicators'] ?? 'Multiple IOCs detected' }}</td>
                    </tr>
                </table>
            </div>
        </div>
    @endif
    
    <div class="info-box warning">
        <h3 style="margin: 0 0 10px 0; font-size: 16px;">📞 Emergency Contact Information</h3>
        <table style="width: 100%; margin: 0; font-size: 14px;">
            <tr>
                <td style="width: 30%; font-weight: 600;">Security Team:</td>
                <td>{{ $emergencyContacts['security'] ?? 'security@mscwoundcare.com' }}</td>
            </tr>
            <tr>
                <td style="font-weight: 600;">Incident Response:</td>
                <td>{{ $emergencyContacts['incident'] ?? '(555) 123-SECR' }}</td>
            </tr>
            <tr>
                <td style="font-weight: 600;">Legal/Compliance:</td>
                <td>{{ $emergencyContacts['legal'] ?? 'legal@mscwoundcare.com' }}</td>
            </tr>
            <tr>
                <td style="font-weight: 600;">Executive Alert:</td>
                <td>{{ $emergencyContacts['executive'] ?? 'executives@mscwoundcare.com' }}</td>
            </tr>
        </table>
    </div>
    
    <div style="background-color: var(--msc-red); color: white; padding: 20px; border-radius: 6px; margin-top: 30px;">
        <p style="margin: 0; font-size: 14px; text-align: center; font-weight: 600;">
            🚨 THIS IS A SECURITY INCIDENT 🚨<br>
            Treat this information as CONFIDENTIAL and follow established incident response procedures.<br>
            Do not discuss this incident outside of authorized personnel.
        </p>
    </div>
    
    <p style="margin-top: 20px; font-size: 12px; color: var(--text-muted); text-align: center;">
        Incident ID: {{ $incidentId }} | Generated: {{ now()->format('Y-m-d H:i:s T') }}
    </p>
@endsection
```

## 4. Performance Alert Template

**File**: `resources/views/emails/performance-alert.blade.php`

```blade
@extends('emails.layout')

@section('title', 'Performance Alert - MSC Wound Care Portal')
@section('aria-label', 'System performance warning notification')

@section('content')
    <h1 style="color: #f59e0b;">⚡ System Performance Alert</h1>
    
    <p>Dear MSC System Administrator,</p>
    
    <p>Our monitoring systems have detected performance issues that may impact user experience. Immediate attention may be required.</p>
    
    <div class="info-box warning">
        <h2 style="margin: 0 0 15px 0; font-size: 18px;">📊 Performance Metrics</h2>
        <table style="width: 100%; margin: 0;">
            <tr>
                <td style="width: 30%; font-weight: 600; color: var(--msc-blue); padding: 8px 0;">Alert Level:</td>
                <td style="padding: 8px 0;">
                    <span style="background-color: #f59e0b; color: white; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600;">
                        {{ strtoupper($alertLevel) }}
                    </span>
                </td>
            </tr>
            <tr>
                <td style="font-weight: 600; color: var(--msc-blue); padding: 8px 0;">Detected:</td>
                <td style="padding: 8px 0;">{{ $detectedAt->format('F j, Y g:i:s A T') }}</td>
            </tr>
            <tr>
                <td style="font-weight: 600; color: var(--msc-blue); padding: 8px 0;">Duration:</td>
                <td style="padding: 8px 0;">{{ $detectedAt->diffForHumans() }}</td>
            </tr>
            <tr>
                <td style="font-weight: 600; color: var(--msc-blue); padding: 8px 0;">Affected Services:</td>
                <td style="padding: 8px 0;">{{ implode(', ', $affectedServices) }}</td>
            </tr>
        </table>
    </div>
    
    <div style="margin: 30px 0;">
        <h2>📈 Current Performance Metrics</h2>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Metric</th>
                    <th>Current</th>
                    <th>Threshold</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($performanceMetrics as $metric)
                <tr>
                    <td style="font-weight: 600;">{{ $metric['name'] }}</td>
                    <td style="font-family: monospace;">{{ $metric['current'] }}</td>
                    <td style="font-family: monospace;">{{ $metric['threshold'] }}</td>
                    <td>
                        @if($metric['status'] === 'critical')
                            <span class="badge badge-denied">CRITICAL</span>
                        @elseif($metric['status'] === 'warning')
                            <span style="background-color: #f59e0b; color: white; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600;">WARNING</span>
                        @else
                            <span class="badge badge-approved">OK</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    
    @if(isset($impactAnalysis))
        <div style="margin: 30px 0;">
            <h2>🎯 Impact Analysis</h2>
            <div class="info-box">
                <table style="width: 100%; margin: 0; font-size: 14px;">
                    <tr>
                        <td style="width: 30%; font-weight: 600; color: var(--msc-blue); padding: 4px 0;">Active Users:</td>
                        <td style="padding: 4px 0;">{{ $impactAnalysis['active_users'] ?? 'Unknown' }}</td>
                    </tr>
                    <tr>
                        <td style="font-weight: 600; color: var(--msc-blue); padding: 4px 0;">Affected Operations:</td>
                        <td style="padding: 4px 0;">{{ $impactAnalysis['affected_operations'] ?? 'Multiple' }}</td>
                    </tr>
                    <tr>
                        <td style="font-weight: 600; color: var(--msc-blue); padding: 4px 0;">Error Rate:</td>
                        <td style="padding: 4px 0;">{{ $impactAnalysis['error_rate'] ?? 'Elevated' }}</td>
                    </tr>
                    <tr>
                        <td style="font-weight: 600; color: var(--msc-blue); padding: 4px 0;">SLA Status:</td>
                        <td style="padding: 4px 0;">
                            @if(($impactAnalysis['sla_breach'] ?? false))
                                <span style="color: var(--msc-red); font-weight: 600;">BREACH RISK</span>
                            @else
                                <span style="color: #10b981; font-weight: 600;">WITHIN LIMITS</span>
                            @endif
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    @endif
    
    <div style="margin: 30px 0;">
        <h2>🔧 Recommended Actions</h2>
        <div class="info-box warning">
            <ol style="margin: 0; padding-left: 20px; font-size: 14px;">
                @foreach($recommendedActions as $action)
                    <li>{{ $action }}</li>
                @endforeach
            </ol>
        </div>
    </div>
    
    @if(isset($automaticActions) && count($automaticActions) > 0)
        <div style="margin: 30px 0;">
            <h2>🤖 Automatic Responses Activated</h2>
            <div class="info-box success">
                <ul style="margin: 0; padding-left: 20px; font-size: 14px;">
                    @foreach($automaticActions as $action)
                        <li>{{ $action }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif
    
    <div style="text-align: center; margin: 30px 0;">
        <a href="{{ $monitoringDashboardLink }}" class="button button-primary">
            View Live Monitoring Dashboard
        </a>
        <p style="margin: 15px 0 0 0; font-size: 14px;">
            Real-time system performance and health metrics.
        </p>
    </div>
    
    @if(isset($resourceUtilization))
        <div style="margin: 30px 0;">
            <h2>💻 Resource Utilization</h2>
            <div class="info-box">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin: 0;">
                    <div>
                        <h4 style="margin: 0 0 10px 0; font-size: 14px;">Server Resources</h4>
                        <table style="width: 100%; font-size: 12px;">
                            <tr>
                                <td>CPU Usage:</td>
                                <td style="font-family: monospace;">{{ $resourceUtilization['cpu'] ?? 'N/A' }}</td>
                            </tr>
                            <tr>
                                <td>Memory Usage:</td>
                                <td style="font-family: monospace;">{{ $resourceUtilization['memory'] ?? 'N/A' }}</td>
                            </tr>
                            <tr>
                                <td>Disk I/O:</td>
                                <td style="font-family: monospace;">{{ $resourceUtilization['disk_io'] ?? 'N/A' }}</td>
                            </tr>
                        </table>
                    </div>
                    <div>
                        <h4 style="margin: 0 0 10px 0; font-size: 14px;">Database Performance</h4>
                        <table style="width: 100%; font-size: 12px;">
                            <tr>
                                <td>Connections:</td>
                                <td style="font-family: monospace;">{{ $resourceUtilization['db_connections'] ?? 'N/A' }}</td>
                            </tr>
                            <tr>
                                <td>Query Time:</td>
                                <td style="font-family: monospace;">{{ $resourceUtilization['avg_query_time'] ?? 'N/A' }}</td>
                            </tr>
                            <tr>
                                <td>Lock Waits:</td>
                                <td style="font-family: monospace;">{{ $resourceUtilization['lock_waits'] ?? 'N/A' }}</td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    @endif
    
    <div class="info-box">
        <h3 style="margin: 0 0 10px 0; font-size: 16px;">📞 Escalation Contacts</h3>
        <table style="width: 100%; margin: 0; font-size: 14px;">
            <tr>
                <td style="width: 30%; font-weight: 600;">On-Call Engineer:</td>
                <td>{{ $escalationContacts['oncall'] ?? 'oncall@mscwoundcare.com' }}</td>
            </tr>
            <tr>
                <td style="font-weight: 600;">System Administrator:</td>
                <td>{{ $escalationContacts['sysadmin'] ?? 'sysadmin@mscwoundcare.com' }}</td>
            </tr>
            <tr>
                <td style="font-weight: 600;">DevOps Team:</td>
                <td>{{ $escalationContacts['devops'] ?? 'devops@mscwoundcare.com' }}</td>
            </tr>
        </table>
    </div>
    
    <div style="background-color: var(--msc-light-blue); padding: 20px; border-radius: 6px; margin-top: 30px;">
        <p style="margin: 0; font-size: 14px; text-align: center;">
            <strong>Monitoring Alert System</strong><br>
            This alert was generated by our automated monitoring system.<br>
            For questions: <a href="mailto:monitoring@mscwoundcare.com" style="color: var(--msc-blue);">monitoring@mscwoundcare.com</a>
        </p>
    </div>
@endsection
```

## Usage Instructions

### 1. Implementation

Place templates in:
```bash
resources/views/emails/
├── system-maintenance.blade.php
├── backup-complete.blade.php
├── security-breach.blade.php
└── performance-alert.blade.php
```

### 2. Service Integration

Example usage in your `MailgunNotificationService`:

```php
// System maintenance notice
public function sendMaintenanceNotice(
    array $recipients, 
    Carbon $start, 
    Carbon $end, 
    array $newFeatures = []
) {
    $data = compact('maintenanceStart', 'maintenanceEnd', 'newFeatures');
    
    foreach($recipients as $email) {
        $this->sendEmail(
            'emails.system-maintenance',
            $data,
            $email,
            "🔧 Scheduled Maintenance - MSC Wound Care Portal",
            'system-maintenance'
        );
    }
}

// Security breach alert
public function sendSecurityAlert(array $incidentData)
{
    $adminEmails = User::where('role', 'admin')->pluck('email');
    
    foreach($adminEmails as $email) {
        $this->sendEmail(
            'emails.security-breach',
            $incidentData,
            $email,
            "🚨 URGENT: Security Incident Alert - {$incidentData['incidentId']}",
            'security-breach'
        );
    }
}
```

### 3. Automation Integration

These templates work well with:
- **Monitoring Systems**: Automated alerts based on system metrics
- **Backup Solutions**: Scheduled reporting of backup completion
- **Security Tools**: Real-time threat detection notifications
- **Maintenance Schedulers**: Automated advance notices

---

**Last Updated**: August 4, 2025  
**Next**: [Template Customization Guide](customization-guide.md)
