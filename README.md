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

* Docker Compose (under construction)

Setup instructions:

* Save the project on your server (download [here](https://github.com/ivancarlosti/ddnsonroute53/zipball/master))
* Set database information on `dbconfig.php`
* Run `setup.php` on browser to set username and password
* Run `index.php` on browser to login
* Access `Manage AWS Credentials` menu and fill required fields
* Access `Manage DDNS Entries` to add, edit and delete DDNS entries
* (optional) Access `Manage Users` to change user password, create new users, add/edit reCAPTCHA keys 

IAM required policy, note that this policy restricts access to only manage subdomains from `subdomain.example.com`, remove the condition if you want to use root domain. Remember to update `YOURZONEID` value to related domain zone ID and `subdomain.example.com.` to your subdomain:

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


DDNS cURL update format (friendly format tested on TP-Link Omada routers):

`https://[username]:[password]@example.com/update.php?hostname=[domain]&myip=[IPv4]`

To simplify the process, `[domain]` is also used as `[username]`.

Omada Update URL: `https://[USERNAME]:[PASSWORD]@example.com/update.php?hostname=[DOMAIN]&myip=[IP]`

ToDo:

* HTML beautification
* Docker Compose samples with/without MariaDB and reverse proxy
