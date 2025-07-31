# DDNS on Route53
Router friendly client to update AWS Route53 entries for Dynamic DNS funtionality

<!-- buttons -->
[![Stars](https://img.shields.io/github/stars/ivancarlosti/ddnsonroute53?label=⭐%20Stars&color=gold&style=flat)](https://github.com/ivancarlosti/ddnsonroute53/stargazers)
[![Watchers](https://img.shields.io/github/watchers/ivancarlosti/ddnsonroute53?label=Watchers&style=flat&color=red)](https://github.com/sponsors/ivancarlosti)
[![Forks](https://img.shields.io/github/forks/ivancarlosti/ddnsonroute53?label=Forks&style=flat&color=ff69b4)](https://github.com/sponsors/ivancarlosti)
[![GitHub last commit](https://img.shields.io/github/last-commit/ivancarlosti/ddnsonroute53?label=Last%20Commit)](https://github.com/ivancarlosti/ddnsonroute53/commits)
[![GitHub commit activity](https://img.shields.io/github/commit-activity/m/ivancarlosti/ddnsonroute53?label=Activity)](https://github.com/ivancarlosti/ddnsonroute53/pulse)  
[![GitHub Issues](https://img.shields.io/github/issues/ivancarlosti/ddnsonroute53?label=Issues&color=orange)](https://github.com/ivancarlosti/ddnsonroute53/issues)
[![License](https://img.shields.io/github/license/ivancarlosti/ddnsonroute53?label=License)](LICENSE)
[![Security](https://img.shields.io/badge/Security-View%20Here-purple)](https://github.com/ivancarlosti/ddnsonroute53/security)
[![Code of Conduct](https://img.shields.io/badge/Code%20of%20Conduct-2.1-4baaaa)](https://github.com/ivancarlosti/ddnsonroute53?tab=coc-ov-file)
<!-- endbuttons -->

## Requirement:

* MySQL/MariaDB
* PHP 8+
* HTTP server

or

* [Docker Compose](https://docs.docker.com/engine/install/)

## Hosting instructions (PHP hosting):

* Save the project on your server (download [here](https://github.com/ivancarlosti/ddnsonroute53/releases/latest))
* Set database information on `dbconfig.php`


## Hosting instructions (Docker Compose with Apache+PHP):

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

## Hosting instructions (Docker Compose with Apache+PHP+MariaDB+Traefik):

* Download `/docker` files on your server, example:
```
curl -o .env https://raw.githubusercontent.com/ivancarlosti/ddnsonroute53/main/docker/.env
curl -o docker-compose.yml https://raw.githubusercontent.com/ivancarlosti/ddnsonroute53/main/docker/docker-compose-full.yml
```
* Edit both `.env`, `docker-compose.yml` files
  * This Docker Compose contains following services, adapt it for your needs:
    * PHP + nginx + mysqli as webserver
    * MariaDB as database
    * Traefik to get automatic SSL certificate and reverse proxy
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

<!-- footer -->
---

## 🧑‍💻 Consulting and technical support
* For personal support and queries, please submit a new issue to have it addressed.
* For commercial related questions, please contact me directly for consulting costs. 

## 🩷 Project support
| If you found this project helpful, consider |
| :---: |
[**buying me a coffee**][buymeacoffee], [**donate by paypal**][paypal], [**sponsor this project**][sponsor] or just [**leave a star**](../..)⭐
|Thanks for your support, it is much appreciated!|

## 🌐 Connect with me
[![Instagram](https://img.shields.io/badge/Instagram-@ivancarlos-E4405F)](https://instagram.com/ivancarlos)
[![LinkedIn](https://img.shields.io/badge/LinkedIn-@ivancarlos-0077B5)](https://www.linkedin.com/in/ivancarlos)
[![Threads](https://img.shields.io/badge/Threads-@ivancarlos-808080)](https://threads.net/@ivancarlos)
[![X](https://img.shields.io/badge/X-@ivancarlos-000000)](https://x.com/ivancarlos)  
[![Discord](https://img.shields.io/badge/Discord-@ivancarlos.me-5865F2)](https://discord.com/users/ivancarlos.me)
[![Signal](https://img.shields.io/badge/Signal-@ivancarlos.01-2592E9)](https://icc.gg/.signal)
[![Telegram](https://img.shields.io/badge/Telegram-@ivancarlos-26A5E4)](https://t.me/ivancarlos)  
[![Website](https://img.shields.io/badge/Website-ivancarlos.me-FF6B6B)](https://ivancarlos.me)
[![GitHub Sponsors](https://img.shields.io/github/sponsors/ivancarlosti?label=GitHub%20Sponsors&color=ffc0cb)][sponsor]

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
