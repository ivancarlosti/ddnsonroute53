# DDNS on Route53
Router friendly client to update AWS Route53 entries for Dynamic DNS funtionality

<a target="_blank" href="https://github.com/ivancarlosti/ddnsonroute53"><img src="https://img.shields.io/github/stars/ivancarlosti/ddnsonroute53?style=flat" /></a>
<a target="_blank" href="https://github.com/ivancarlosti/ddnsonroute53"><img src="https://img.shields.io/github/last-commit/ivancarlosti/ddnsonroute53" /></a>
[![GitHub Sponsors](https://img.shields.io/github/sponsors/ivancarlosti?label=GitHub%20Sponsors)](https://github.com/sponsors/ivancarlosti)
[![download_aws_sdk](https://github.com/ivancarlosti/ddnsonroute53/actions/workflows/download_aws_sdk.yml/badge.svg)](https://github.com/ivancarlosti/ddnsonroute53/actions/workflows/download_aws_sdk.yml)

## Requirement:

* MySQL/MariaDB
* PHP 8+ [([note](#hosting-note))
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

## To Do:

* HTML beautification
* Build releases using Compose to populate AWS SDK dinamically

## Hosting note:

Using PHP with the Suhosin patch is not recommended, but is common on some Ubuntu and Debian distributions. To modify `suhosin.ini`, add the following line.

```
suhosin.executor.include.whitelist = phar
```

---

## Donation

| If you found this project helpful, consider |
| :---: |
[**buying me a coffee**][buymeacoffee], [**donate by paypal**][paypal], [**sponsor this project**][sponsor] or just [**leave a star**](../..)⭐
|Thanks for your support, it is much appreciated!|

## License

[MIT](LICENSE) © [Ivan Carlos][ivancarlos]

[cc]: https://docs.github.com/en/communities/setting-up-your-project-for-healthy-contributions/adding-a-code-of-conduct-to-your-project
[contributing]: https://docs.github.com/en/articles/setting-guidelines-for-repository-contributors
[security]: https://docs.github.com/en/code-security/getting-started/adding-a-security-policy-to-your-repository
[support]: https://docs.github.com/en/articles/adding-support-resources-to-your-project
[it]: https://docs.github.com/en/communities/using-templates-to-encourage-useful-issues-and-pull-requests/configuring-issue-templates-for-your-repository#configuring-the-template-chooser
[prt]: https://docs.github.com/en/communities/using-templates-to-encourage-useful-issues-and-pull-requests/creating-a-pull-request-template-for-your-repository
[funding]: https://docs.github.com/en/articles/displaying-a-sponsor-button-in-your-repository
[ivancarlos]: https://ivancarlos.me
[buymeacoffee]: https://www.buymeacoffee.com/ivancarlos
[paypal]: https://icc.gg/donate
[sponsor]: https://github.com/sponsors/ivancarlosti
