# EspoSecSignAuth
This [EspoCRM](https://www.espocrm.com) [SecSign](www.secsign.com) plugin adds two-factor authentication to your CRM login.

## Installation
To install this plugin either download the provided release or package the `files`, `scripts` folders and `manifest.json` to a zip.
In your EspoCRM installation go to Administration->Extensions, upload the .zip file and click install.  
Now, with the plugin installed you can find a new option `SecSign` for authentication method in Administration->Authentication.
**Activating it will enforce 2FA for all users!**
The users will have to download the SecSign app for their device and create an ID with their e-mail address as ID name.

The login then works as follows:
- enter your usual credentials
- press login
- get a challenge
![](https://i.imgur.com/W6PvXr9.png)
- approve the challenge on your phone
![](https://i.imgur.com/IE7EG9i.png)
- press check in espo
- voila.

## Development
This plugin is in a quite usable state and is in-use for a while now.
It might make sense to add some more configuration options, though (e.g. issue #1).
