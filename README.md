# Bitfinex-Autolend-Bot
PHP bot to manage currency lending on Bitfinex

This bot facilitates margin lending of currencies on [Bitfinex](http://bitfinex.whalepool.io). It's aim is to enable you to extract the maximum return by automatically managing your lending based on simple rules. The rules provided by default have been used to offer USD funding at a annual return of around 27.5%, almost 10% more than various margin managing services.

The bot generates a report for each currency, allowing you to check your returns and improve your lending rules to increase profits. This report is designed to be created every hour. It reports the percentage of your funds being lent, the rate at which it is being lent (including funds not lent), daily profit and overall profit. Each hourly update is coloured to denote the extent your funds are being loaned out.

## Installation

1. Download or clone the Git
   * It should work on any system including and above PHP5
   * It will require the PHP CURL extension
   * It will require the PHP XML extension
2. Login to your Bitfinex account and navigate to the [API page](http://bitfinex.com/api)
3. Select 'Create New Key' from the bottom of the page and give it the following permissions ![API options](https://s14.postimg.org/jummsxmpt/Screenshot_at_2018-01-22_22-35-33.png)
   * Do not give it any other write permissions, as that will compromise your account if your API keys are obtained by a malicious individual
4. Click 'Generate New API Key' and follow the security steps to obtain your API key and secret
5. The bot comes with two preconfigured settings, one for lending USD and the other for SAN. These are found in the package as BFX-USD.php and BFX-SAN.php. Open the one you wish to lend your funds for, or if there is no file for your chosen currency, open one and save it as a new file, after changing the line ```$a->currency = 'usd'``` to be equal to the currency you'd like to lend, e.g. 'ltc'. This field is case insensitive
   * Enter your API key for the variable, ```$a->apiKey```
   * Enter your API secret for the variable, ```$a->apiSecret```
   * Enter a file-path where you'd like the report to be saved for the variable, ```$a->reportFile```
6. Your bot is now setup and can be run by executing the file, e.g. ```php BFX-USD.php```

## Cron

For the HTML report to work properly and to set up your bot to lend at the optimal level, you need to setup a cron job to run your bot. To do this run ```crontab -e``` and add:

```5 * * * * php /{absolute-path-to-directory}/BFX-USD.php```

Where BFX-USD.php is the name of your bot configuration file. This will run that bot every hour.

## Bot Options

The bot has a number of options, most of which allow you to tweak the bot's lending in the search for maximum profits. You will find all of these predefined in the files BFX-USD.php and BFX-SAN.php

1. ```tries``` - number of times to retry API requests that fail
2. ```placeAboveLowestAsk``` - number of units to offer lending above the lowest ask, e.g. if the lowest ask is for 0.05 and placeAboveLowestAsk = 1,000,000 the bot will lend out at a rate where there are 1,000,000 units offered above the lowest ask. As a specific example if the lowest ask for USD is at 0.05 and placeAboveLowestAsk = 1,000,000, the bot will look 1 million dollars above the highest ask and offer at that rate. This is a mechanism, which is used to obtain more favourable rates
3. ```minimumRate``` - the rate the bot will lend at will be set to this if calculate rate to offer at is below it
4. ```periodSchedule``` - this allows you to set the number of days to offer funding for within a range of lending rates, e.g. 0.05 => 3 - all lending between 0.05 and 0.059r will be for 3 days; all lending lower than the lowest specified rate will be for the period of the lowest specified rate, e.g. if the lowsest specified rate is 0.04 => 2 lending between 0 and 0.39r will be for a period of 2 days all lending higher than the highest specified rate will be for the period of the highest specified rate, e.g. if the highest specified rate is 0.1 => 30 lending more than 0.1 will be for a period of 30 days

## Report

The report looks like this:

![Bitfinex margin funding report screenshot](https://s13.postimg.org/6adzx7asn/image.png)

The numbered columns at the top correspond to hours. Daily profit is also recorded at the end of each day (not visible in the screenshot).

## Improvement

The settings in the BFX-USD.php file have been tweaked so as to increase profit. However, for those who would like to gain even bettter returns it would be useful to also set the placeAboveLowestAsk variable on a sliding scale by rate. When rates go particularly high, above 0.1, it would be beneficial to offer with a higher placeAboveLowestAsk, as the current settings are not optimal for catching FOMO on rates that occasionally spike up to around 0.8. I don't have the time to change this at the moment.

## Tips

If you want to tip the developer - Bitcoin: 1Nq3bNj2YamANmS66S21jMk34yGbyTz5xv




