# Example: httpd + ldap

This Example shows how to use sca with httpd and ldap using docker.

## Prepare setup

1. Start system using `docker-compose up -d`
1. Visit http://localhost
1. Login using one of the following credentials:

|Username|Password|Type|
|---|---|---|
|rainbow|password|admin|
|proceme|password|user|

If something goes wrong, check the log using:
```
docker logs -f httpd-ldap_sca_1
```

## Using sca

1. Login using the admin account `rainbow`.
1. Add the server `test.example.com` at http://localhost/servers#add
1. Sca should be able to connet to the system and update its authorized_keys file. You can verify this by checking whether there is an `Synced successfully` next to the server. 
