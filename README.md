# DDNS on Route53
Router friendly client to update AWS Route53 entries for Dynamic DNS funtionality

<a target="_blank" href="https://github.com/ivancarlosti/ddnsonroute53"><img src="https://img.shields.io/github/stars/ivancarlosti/ddnsonroute53?style=flat" /></a>
<a target="_blank" href="https://github.com/ivancarlosti/ddnsonroute53"><img src="https://img.shields.io/github/last-commit/ivancarlosti/ddnsonroute53" /></a>
[![GitHub Sponsors](https://img.shields.io/github/sponsors/ivancarlosti?label=GitHub%20Sponsors)](https://github.com/sponsors/ivancarlosti)

Please help funding the project.

`/aws` folder comes from https://github.com/aws/aws-sdk-php/releases, package `aws.zip`.

Requirement:

* MySQL/MariaDB
* PHP 8+
* HTTP server

or

* [Docker Compose](https://docs.docker.com/engine/install/)

## Hosting instructions (PHP hosting):

* Save the project on your server (download [here](https://github.com/ivancarlosti/ddnsonroute53/zipball/master))
* Set database information on `dbconfig.php`


## Hosting instructions (Docker Compose):

* Download `/docker` files on your server, example:
```
curl -o .env https://raw.githubusercontent.com/ivancarlosti/ddnsonroute53/main/docker/.env
curl -o docker-compose.yml https://raw.githubusercontent.com/ivancarlosti/ddnsonroute53/main/docker/docker-compose.yml
```
* Edit both `.env`, `docker-compose.yml` files
  * This Docker Compose contains following services, adapt it for your needs:
    * MariaDB as database
    * PHP+Apache as webserver
    * git to clone this repository,
    * Traefik to get automatic SSL certificate
* Start Docker Compose, example:
```
docker compose pull && docker compose up -d
```

## Service setup:

* Run `setup.php` on browser to set username and password
* Run `index.php` on browser to login
* Access `Manage AWS Credentials` menu and fill required fields
* Access `Manage DDNS Entries` to add, edit and delete DDNS entries
* (optional) Access `Manage Users` to change user password, create new users, add/edit reCAPTCHA keys 

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
			"Action": [
				"route53:GetChange",
				"route53:ListHostedZones"
			],
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

## To Do:

* HTML beautification
* Build releases using Compose to populate AWS SDK dinamically
