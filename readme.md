Remote Asset Synchronisation Task
=================================

### Downloads files from a remote server running SilverStripe

A task that downloads accessable files in assets/* from a target server

Usefull when you want to update assets onto a development 
environment, without having to do a full file snapshot.

![Comparing the two file lists](images/download1.png)
![Downloading in progress](images/download2.png)


### Installation

 - **composer install --dev otago/remote-asset-download**
 - create your own yml **app/_config/remoteassetssync.yml** (see below)
 - Create a user that can read & write assets you want to sync on the target machine. 
   This user is also used to run the task in CLI mode on the local machine.

```
---
Name: RemoteAssetTask
---
OP\RemoteAssetReadFilesController:
  target: https://target.server.org
  user: member@organisation.org
  password: <mypassword>
```

The user must exist in SilverStripe, and have access to assets. you can restrict the user 
to specific files and folders in the SilverStripe CMS.

### How to run it

Open up **/dev/tasks/OP-RemoteAssetTask** in your browser on the local machine. 

You can also run it from the command line with 

**vendor/silverstripe/framework/sake dev/tasks/OP-RemoteAssetTask**


### How it works

GraphQL black magic

When you load the task via HTTP on the local machine, an ajax poll will ask your 
local machine to send a graphql request to the target server. This will return a 
list of files in assets. This result will then be passed back to your local machine,
which will then bulk download these files from the target server.


### Notes

 - The task will stop when it starts running into files that have the same name & id.
 - if a file has a different file and the same id, it will be overwritten
