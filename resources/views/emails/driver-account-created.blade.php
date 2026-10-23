<x-mail::message>
# Hello {{ $driver->name }}

Your ScanTrack driver account has been created and activated by your organization manager.

**Login email:** {{ $email }}  
**Temporary password:** {{ $temporaryPassword }}

**Organization:** {{ $driver->organization?->name ?? 'ScanTrack' }}

Log in to the ScanTrack Mobile App with these credentials. You must change this temporary password immediately after your first login.

If you did not expect this account, please contact the Tap Track administrator.

Thanks,

ScanTrack Administration
</x-mail::message>