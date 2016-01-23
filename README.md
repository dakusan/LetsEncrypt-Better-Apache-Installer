[LetsEncrypt Better Apache Installer v1.0.0<br>
Website: http://www.castledragmire.com/Projects/LetsEncrypt_Better_Apache_Installer](http://www.castledragmire.com/Projects/LetsEncrypt_Better_Apache_Installer)<br>
GitHub: https://github.com/dakusan/LetsEncrypt-Better-Apache-Installer/

# Short Description
Installs SSL/HTTPS certificates via letsencrypt for all domains.

Default configuration is for cPanel.

# Description
* This takes a single VirtualHost domain and will install certificates for all VirtualHosts on the same IP (or if requested, just the given VirtualHost)
* This script can be run through both a _bash_ command line (CLI), and as a web page. Parameter names use a different format for the two
* While this script was originally designed for cPanel, it should work with any apache configuration, given the correct parameters
  * The cPanel specific defaults are:
    * Only looks in a single apache config file, which includes all VirtualHost configurations
    * Using a cPanel script called “whmapi1” to install the certificates
* UTF-8 is assumed for all strings (this is generally the default anyways)

# Examples
## Basic Example

On a cPanel system, this will add SSL certificates for all domains on the same IP as example.com, with each DocRoot/VHost having a different certificate
```bash
Main.php -dexample.com -eyour@email.com
```

## Remote Example
```bash
php Main.php -dexample.com -eyour@email.com \
  -a 'ssh2.sftp://USER:PASSWORD@example.com//etc/httpd/conf/httpd.conf' \
  -c '/^(.*)$/ssh2.sftp:\/\/USER:PASSWORD@example.com\/$1/D' \
  -s 'ssh USER:PASSWORD@example.com "whmapi1 installssl domain=%s crt=%s key=%s cab=%s"' \
  -l 'ssh USER:PASSWORD@example.com "letsencrypt ?Params?"'
```

# Other Notes
I actually had this pretty much complete a month ago, when letsencrypt first came out. I am very annoyed at myself for waiting so long to get it released.

While there are currently a lot of other solutions out there for this, I feel mine is a good generic solution that can fit any situation with all the options. That being said, sorry for including so many parameters! I'm kind of GNUish that way.

All of the defaults can also be modified in the Config.php file.

# Requirements
1. letsencrypt must already be installed. If it is not part of the path, make sure to pass the “LECmd” parameter
2. If using cPanel scripts, cPanel must already be installed on the system
3. By default, domain VirtualHosts can only be located in a single apache configuration file. This is easily fixable with an example below for the “ApacheConfPath” parameter

# This works by
1. Finds the IP of a given domain in the apache conf file(s)
2. Finds all VirtualHosts bound to that IP in the apache conf(s) and extracts all of their ServerName and ServerAlias domains
3. Runs all these found domains through letsencrypt to create a master certificate for the IP
4. Installs the certificate for each VirtualHost on the IP

# Parameters
## ******IMPORTANT PARAMETER WARNING</font>******
* Any parameter marked as “UnsafeVar” will only be usable if the “AllowConfigOverride” PHP configuration variable is set as true/On. It is currently set as “On”
  * If the user is not completely trusted, this needs to be set to false; as otherwise, they can read any file or run any system command as the current user

## Other parameter info
* Any parameter marked as “File” uses file_get_contents(), so it can contain PHP fopen wrappers available on your system like SSH (https://secure.php.net/manual/en/wrappers.php)
  * Example (remote file via SSH): ssh2.sftp://user:pass@example.com:22/etc/httpd.conf
  * Example (multiple remote apache config files via SSH): ssh2.exec://user:pass@example.com/find /etc/httpd/conf* -type f -print0 | xargs -0 cat
* Any parameter marked as “Exec” will be run with exactly what is given through the command line via php.exec()
  * Paths and parameters that you give are NOT ESCAPED, so you must do so yourself
  * This generally just entails putting quotes around any path or parameter that includes a space
  * Example: ls -l 'my file.conf'
* For “BOOL” parameters, only the first case-insensitive character is looked at, and only the following characters evaluate to true: 1, y, t

# Parameters
| CLI Short | CLI Long | Web | Flags | Description |
| --- | --- | --- | --- | --- |
| -d | --domain | Domain | Required | The domain to process that is the ServerName on the VirtualHost (not a ServerAlias) |
| -e | --email | Email | Required | Your email |
| -i | --ignore | Ignore |  | A space delineated list of domains [single parameter] to NOT send to letsencrypt<br><br>**Example**: "www.example.com skip.example.com" |
| -h | --help | Help |  | This help screen |
| -a | --apache-conf | ApacheConf | UnsafeVar, File | Override the location of the apache config file<br><br>**Default**: /etc/httpd/conf/httpd.conf |
| -l | --le-cmd | LECmd | UnsafeVar, Exec | The command to run to execute letsencrypt<br><br>?Params? in the string is replaced with all the parameters, including the below LEParams parameter. It is required, and must not be enclosed in quotes<br><br>**Default**: letsencrypt ?Params?<br><br>**Example**: ssh USER:PASSWORD@example.com "/home/my user/letsencrypt" ?Params? |
| -p | --le-params | LEParams | UnsafeVar, Exec | The parameters to pass to the letsencrypt command (LECmd)<br><br>**Default**: certonly --webroot -t --renew-by-default --agree-tos --duplicate |
| -s | --ssl-install-cmd | SSLInstallCmd | UnsafeVar, Exec | The command to run to install HTTPS/SSL certificates<br><br>It is a PHP sprintf format string which receives 4 variables, which are, in order:<br>1) The Domain<br>2) The Certificate Data<br>3) The Private Key Data<br>4) The Certificate Authority Chain (CA) Data<br><br>When passed to sprintf, all of these variables are already escaped with quotes<br><br>**Default**: whmapi1 installssl domain=%s crt=%s key=%s cab=%s |
| -c | --cert-path-ovr | CertPathOvr | UnsafeVar, File | A PHP PREG-format regular expression that the certificate path returned from letsencrypt will be ran through<br><br>**Format**: /searching_string/replace_string/modifiers<br><br>The PCRE delimiters must be ord()<=127<br><br>Default is blank (to ignore this parameter)<br><br>**Example**: /^(.*)$/ssh2.sftp:\/\/USER:PASS@example.com:22$1/D |
| -t | --timestamp-type | TimestampType |  | What current time information to show for each log line<br><br>Must be a single parameter with flags separated by a space or comma<br><br>**Can be a combination of the following flags**:<br>1) DailyDate: Show the date before an info log line when the day has rolled over<br>2) Date: Show the date on every info log line<br>3) Time: Show the time on every info log line<br><br>The timestamp is never given when the help screen is invoked<br><br>**Default**: DailyDate,Time |
| -o | --coloring-type | ColoringType |  | Whether to color output using: xterm, html, none<br><br>If set to “UseInterfaceType”, it is set to either html or xterm, depending on the interface you are using<br><br>If an invalid value is given, “none” is assumed<br><br>**Default**: UseInterfaceType |
| -b | --distribution | Distribution |  | The combination of domains for created certificates. The values can be:<br><br>1) **GivenDomainOnly**: Only create a certificate for the domains whose vhost-document-root-path matches that of the given domain<br><br>For all virtual host domains, including aliases, that are on the IP of the given domain:<br><br>2) **AllInOne**: Include all domains in a single certificate via SAN (Subject Alternative Names)<br><br>3) **SeparateVHosts**: Create a separate certificate for each vhost-document-root-path (Also uses SAN)<br><br>**Default**: SeparateVHosts |
| -u | --url-encode-prms | URLEncodePrms | BOOL | If true, the parameters passed to “SSLInstallCmd” need to be URL encoded<br><br>**Default**: true |
| -f | --install-anyways | InstallAnyways | BOOL | If true, even if an error occured while creating one of the SSL certificates, the remaining certificates are installed<br><br>Only valid for Distrubtion=SeparateVHosts<br><br>**Default**: true |

# Licenses
This is under [Dakusan License v2.0](http://www.castledragmire.com/Copyright), which is the 4 clause BSD