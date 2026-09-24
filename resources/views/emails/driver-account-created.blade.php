<x-mail::message>
# Hello {{ $driver->name }}

Your Tap Track driver account has been created by the administrator.

**Login email:** {{ $email }}  
**Temporary password:** {{ $temporaryPassword }}

Please use these credentials to log in to the Tap Track driver application. For security, you will be required to change this password after your first login.

If you did not expect this account, please contact the Tap Track administrator.

Thank you,  
Tap Track Administration
</x-mail::message>