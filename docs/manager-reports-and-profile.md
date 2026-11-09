# Manager reports and profile

Run `php artisan migrate` against the shared backend before using attachments. For local PHP installations, set `upload_max_filesize=5M` and `post_max_size=20M`; the Docker image includes these settings. Run `php artisan storage:link` if the public storage link is missing. Keep `APP_URL` set to the backend's public origin and the mobile app's `EXPO_PUBLIC_API_URL` pointing to that same backend with `/api` appended.

Driver reports continue to use `POST /api/driver/issues`. Existing JSON submissions remain supported. Multipart submissions can include `attachments[]` (at most three files, 5 MB each; JPG, PNG, WebP or PDF). Mobile provides photo selection through its existing Expo ImagePicker dependency. Reusing `client_request_id` returns the existing report without duplicating files or notifications. Files are removed if the database transaction fails.

Managers open **Reports** to search by driver or explanation and filter by review status or submission date in Manila time. The inbox refreshes every 30 seconds and on window focus, with manual refresh and failure feedback. New reports also appear in the dashboard notification bell with a link to the report. Open a report to download attachments and approve or reject it with a required explanation. Review decisions use the same transactional service for web and mobile. Queue-linked approvals require explicit removal confirmation and only affect the original waiting entry. Reviews are final; a second decision is rejected. General report approvals leave the queue unchanged. Reports belong to their submission organization; managers only see and review their own organization's records. Signed file links are bearer links valid for 15 minutes; refreshing the report renews them.

The driver's **My Requests** screen polls the shared API, displays recorded decisions and attachments, and confirms successful submission. Existing mobile polling handles synchronization between devices.

Managers open **Profile → Edit Profile** to update first/middle/last name, email, contact number, address and photo. Photos accept JPG, PNG or WebP up to 2 MB and can be replaced or removed. The update always targets the authenticated manager and ignores supplied account IDs, roles and organization IDs. Old photos are removed after the replacement has saved. Text changes and photos appear after redirect and refresh, using the same account record exposed by the mobile API.

Verification:

```text
php artisan test
npm run build
```

From the mobile project:

```text
npm run test:ui
npx tsc --noEmit
```

For device verification, submit a report with a photo from a driver account, open it under the same organization's manager account on the web, review it, and refresh My Requests. Verify a different organization's manager cannot open the report. Edit the manager profile, refresh the page, replace the photo, and confirm the previous photo is no longer used.
