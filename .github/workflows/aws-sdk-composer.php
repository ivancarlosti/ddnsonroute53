name: Install AWS SDK via Composer

on: [push, pull_request]

jobs:
  install-aws-sdk:
    runs-on: ubuntu-latest
    steps:
      - name: Checkout repository
        uses: actions/checkout@v4

      - name: Install PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2' # Adjust to your PHP version
          tools: composer

      - name: Install dependencies via Composer
        run: composer install --no-dev --optimize-autoloader

      - name: Verify AWS SDK autoloader
        run: |
          if [ -f "vendor/aws/aws-sdk-php/src/aws-autoloader.php" ]; then
              echo "AWS autoloader exists."
          else
              echo "AWS autoloader missing!" && exit 1
          fi
