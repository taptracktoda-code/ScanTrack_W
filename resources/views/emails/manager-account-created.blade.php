<x-mail::message>
# Hello {{ $manager->name }}

Your ScanTrack manager account has been created.

**Login email:** {{ $manager->email }}  
**Temporary password:** {{ $temporaryPassword }}  
**Organization:** {{ $manager->organization?->name }}

Sign in to the ScanTrack Mobile App and change your temporary password before registering drivers.

Thanks,  
ScanTrack Administration
</x-mail::message>