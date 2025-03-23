# DDNS on Route53
Router friendly client to update AWS Route53 entries for Dynamic DNS funtionality

Please help funding the project.

`/aws` folder comes from https://github.com/aws/aws-sdk-php/releases, package `aws.zip`.

Requirement:

* MySQL/MariaDB
* PHP 8+
* HTTP server

or

* Docker Compose

Setup instructions:

* Set database information on `dbconfig.php`
* Run `/setup.php` on browser to set username and password
* Run `/index.php` on browser to login
* Access `Manage AWS Credentials` menu
* Fill required fields

IAM required policy:

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
            "Resource": [
                "arn:aws:route53:::hostedzone/<yourzoneid>",
                "*"
            ],
            "Condition": {
                "ForAllValues:StringLike": {
                    "route53:ChangeResourceRecordSetsNormalizedRecordNames": "subdomain.example.com."
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
