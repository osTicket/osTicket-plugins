This OAuth plugin provides SSO sign in from many popular external sources
including Google+, GitHub, Facebook, Windows Azure, and many more.

**At the current time, only Google+ authentication is implemented.**

Google+ Authentication
----------------------
To register for Google+ authentication,

* Visit the Google Cloud Console (https://console.developers.google.com/)
* Sign in to Google via a relevant account
* Create a project (name it whatever -- osTicket Help Desk)
* Manage the project, navigate to APIs and Auth / Credentials and create an
  OAuth Client ID
* Register the key with the URL of your helpdesk plus `api/auth/ext`
  (`http://support.mycompany.com/api/auth/ext`). This is called the *Redirect
  URI*
* Navigate to APIs & Auth / Consent Screen and fill in the relevant information
* Navigate to APIs & Auth / APIs and add / enable the *Google+ API*
* Install the plugin in osTicket **1.9**
* Configure the plugin with your Google+ Client ID and Secret
* Configure the plugin to authenticate agents (staff), users or both
* Enable the OAuth plugin
* Log out and back in with Google+
* Enjoy!
