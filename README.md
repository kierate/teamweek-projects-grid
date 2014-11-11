Teamweek projects grid
======================

About
-----
Teamweek projects grid is an "addon" to the Teamweek scheduling service. It allows you to set up requirements per project and then fill in your schedule (on teamweek) until you meet the requirements.
Based on the requirements and the allocated days in your teamweek account the grid shows per project and overall utilisation.


Background
----------
This is something I put together at around mid-2013 at the time I started using Teamweek. It was meant to be a short and small one pager, therefore this:
- has no authentication
- uses flat files for storage rather than a database
- uses a super basic MVC
- implements [v2 of the Teamweek API](https://github.com/toggl/teamweek_api_docs)

It ended up having quite a few lines of code, but this was needed to get some of the detail that I wanted.

In terms of features I built in what I needed, and as I am not currently scheduling a team anymore, I won't be pushing any new features into this project. Having said that if you use it and have any ideas then let me know and I'll see if I can add it.

If you do not have the authentication token from your teamweek account then you will have to use [v3 of the Teamweek API](https://github.com/Teamweek/teamweek/wiki) and implement the OAuth2 workflow.


Installation
------------
Copy this to your PHP enabled server and point your browser to index.php.

Remember: this does not provide authentication, so add a simple .htaccess file or implement something in PHP on top of my code.


Authentication
--------------
There is none, so add a simple .htaccess file or implement something in PHP on top of my code. I do not recommend making this publically accessible if it is connected to your actual Teamweek account.


Requirements
--------------
There are no special requirements for this project really. You just need PHP on the server and permissions for the code to write in the current directory.


Configuration
-------------
You can configure any of the options defined in ProjectsModel::$options by providing their values in the config.json file. This is where you will have to configure your teamweek account details (at the very least).

```json
{
	"teamweek_account_id": "",
	"teamweek_account_auth_token": ""
}
```


Teamweek
--------
[Teamweek](http://teamweek.com) is a really cool way to schedule your team. Give it a go if you need a scheduling tool.

