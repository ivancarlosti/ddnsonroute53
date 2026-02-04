# DDNS on Route53
Router friendly client to update AWS Route53 entries for Dynamic DNS funtionality

<!-- buttons -->

<!-- endbuttons -->

## Requirement:

* [Docker Compose](https://docs.docker.com/engine/install/)
* Domain zone on AWS Route 53
* MySQL/MariaDB
* Keycloak for SSO

## Hosting instructions (Docker Compose with PHP + nginx + mysqli):

* Download `/docker` files on your server, example:
```
curl -o .env https://raw.githubusercontent.com/ivancarlosti/ddnsonroute53/main/docker/.env
curl -o docker-compose.yml https://raw.githubusercontent.com/ivancarlosti/ddnsonroute53/main/docker/docker-compose.yml
```
* Edit both `.env`, `docker-compose.yml` files
  * This Docker Compose contains only PHP + nginx + mysqli as webserver, you can use a reverse proxy for SSL, default exposed port is `5666`, an external MySQL/MariaDB is required
* Start Docker Compose, example:
```
docker compose pull && docker compose up -d
```

## Service setup:

* Run `setup.php` on browser to set username and password
* Run `index.php` on browser to login
* Access `Manage AWS Credentials` menu and fill required fields
* Access `Manage DDNS Entries` to add, edit and delete DDNS entries
* (optional) Access `Manage Users` to create new users, add/edit reCAPTCHA keys
* (optional) Access `View All Logs` to check last 30 days of entries created and/or updated

IAM required policy, remember to update `YOURZONEID` value to related domain zone ID and `subdomain.example.com.` to your domain or subdomain for service usage:

```
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Action": [
        "route53:ChangeResourceRecordSets",
        "route53:ListResourceRecordSets"
      ],
      "Resource": "arn:aws:route53:::hostedzone/YOURZONEID",
      "Condition": {
        "ForAllValues:StringLike": {
          "route53:ChangeResourceRecordSetsNormalizedRecordNames": [
            "*.subdomain.example.com."
          ]
        }
      }
    },
    {
      "Effect": "Allow",
      "Action": "route53:GetChange",
      "Resource": "*"
    }
  ]
}
```

## DDNS cURL update format:

To simplify the process, `[FQDN]` is also used as login for basic auth purposes.

Example: `https://[FQDN]:[PASSWORD]@subdomain.example.com/update.php?hostname=[FQDN]&myip=[IP]`

### Tested DDNS Custom URL:

TP-Link Omada Update URL:
* `https://[USERNAME]:[PASSWORD]@subdomain.example.com/update.php?hostname=[DOMAIN]&myip=[IP]`

Unifi Cloud Gateway custom server:
* `subdomain.example.com/update.php?hostname=%h&myip=%i`

<!-- footer -->
---

## 🧑‍💻 Consulting and technical support
* For personal support and queries, please submit a new issue to have it addressed.
* For commercial related questions, please [**contact me**][ivancarlos] for consulting costs. 

| 🩷 Project support |
| :---: |
If you found this project helpful, consider [**buying me a coffee**][buymeacoffee]
|Thanks for your support, it is much appreciated!|

[ivancarlos]: https://ivancarlos.me
[buymeacoffee]: https://www.buymeacoffee.com/ivancarlos
