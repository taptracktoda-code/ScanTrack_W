# ScanTrack System Schema and Flow

This document maps the main persisted entities and the application flows that create or update them. Solid relationships represent database foreign keys; dotted relationships are application-level conventions or lookups.

## Data Schema

<!-- mermaid-checked: no \n, no em-dash/en-dash, no {} in labels, subgraphs are id["label"], arrows are -->|"label"|, all subgraphs closed by end, ids unique -->
```mermaid
flowchart TD
    subgraph sIdentity["Accounts and settings"]
        sAdmins["admins: id, role, organization_id"]
        sTokens[("personal_access_tokens: tokenable_id, tokenable_type")]
        sSettings[("settings: key, value")]
    end
    subgraph sDirectory["Organization and fleet"]
        sOrganizations["toda_organizations: id, name, status"]
        sDrivers["drivers: id, organization_id, vehicle_number, status"]
        sVehicles["vehicles: id, plate_number, organization_id"]
        sQrCodes["qr_codes: id, driver_id, qr_token, status"]
    end
    subgraph sOperations["Terminal operations"]
        sLogs["driver_logs: id, driver_id, attendance_type, scanned_at"]
        sQueue["organization_queue_entries: id, organization_id, driver_id, status"]
        sActivities["terminal_activities: id, admin_id, driver_id, activity"]
    end

    sOrganizations -->|"has many drivers"| sDrivers
    sOrganizations -->|"has many vehicles"| sVehicles
    sOrganizations -->|"has many managers and admins"| sAdmins
    sDrivers -->|"one QR credential"| sQrCodes
    sDrivers -->|"has many scan logs"| sLogs
    sDrivers -->|"has many queue entries"| sQueue
    sOrganizations -->|"has many queue entries"| sQueue
    sAdmins -->|"registers and reviews drivers"| sDrivers
    sAdmins -->|"queues and serves drivers"| sQueue
    sAdmins -->|"records activity"| sActivities
    sDrivers -->|"activity subject"| sActivities
    sAdmins -.->|"polymorphic tokens"| sTokens
    sDrivers -.->|"vehicle_number matches plate_number"| sVehicles
```

### Relationship Notes

- `drivers.organization_id`, `vehicles.organization_id`, the QR/log/queue references, and activity actor references are backed by foreign keys in migrations.
- A driver has at most one QR row (`qr_codes.driver_id` is unique). Queue rows retain the organization, driver, queue number, status, manager, and serving timestamps.
- Driver-to-vehicle association is resolved by matching `drivers.vehicle_number` to unique `vehicles.plate_number`; it is not declared as a foreign key.
- Driver login accounts are stored in `admins` with role `Driver`. The registration flow finds them using `user_id = DRIVER-<driver id>`; migrations do not define a foreign key from that account to `drivers`.
- `personal_access_tokens` uses Laravel's polymorphic token ownership, so it can store tokens for multiple authenticatable model types.
- `settings` is a key/value table and has no relationship to the operational records.

## System Flows

<!-- mermaid-checked: no \n, no em-dash/en-dash, no {} in labels, subgraphs are id["label"], arrows are -->|"label"|, all subgraphs closed by end, ids unique -->
```mermaid
flowchart TD
    fManager["Manager portal or app"] --> fRegister["Submit driver registration"]
    fRegister --> fCreate["Create pending driver, vehicle, and driver account"]
    fCreate --> fReview["Admin reviews registration"]
    fReview -->|"approve"| fActivate["Activate driver, vehicle, account, and QR"]
    fReview -->|"reject"| fReject["Mark driver and account rejected; deactivate vehicle and QR"]
    fActivate --> fEmail["Send temporary credentials by email"]

    fScanner["Authenticated terminal scanner"] --> fScanRoute["QR scan API route"]
    fScanRoute --> fScanService["QrScanService validates QR, driver, and organization"]
    fScanService -->|"valid and not duplicate"| fDetermine["Choose ENTRY or EXIT from latest log"]
    fDetermine --> fWriteScan["Write driver_logs and terminal_activities"]
    fScanService -->|"invalid, inactive, or duplicate"| fScanFailure["Return scan error"]
    fWriteScan --> fScanResponse["Return scan result"]

    fManager --> fQueueApi["Manager queue API"]
    fQueueApi --> fQueueValidate["Validate active organization and driver"]
    fQueueValidate --> fWaiting["Create WAITING queue entry"]
    fWaiting --> fServe["Serve next: WAITING to SERVING"]
    fServe --> fComplete["Complete service: SERVING to COMPLETED"]
    fWaiting --> fQueueActivity["Record queue activity"]
    fServe --> fQueueActivity
    fComplete --> fQueueActivity
```

### Flow Summary

- Manager or admin registration stores a pending driver, vehicle, and driver-role account. Admin approval activates those records, issues or reactivates a QR credential, records terminal activity, and emails temporary credentials.
- QR scans require authenticated admin access. The scan service validates the QR and related active records, rejects scans within the duplicate-scan window, alternates `ENTRY` and `EXIT`, then writes the scan log and audit activity in a transaction.
- Managers enqueue active drivers in their organization. Queue entries progress through `WAITING`, `SERVING`, and `COMPLETED`; each transition is accompanied by a terminal activity record.
