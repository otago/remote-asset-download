Remote Asset Synchronisation Task
=================================

### Downloads files from a remote server. 

It works by listing the files, and comparing it to the local instance. If there
 are any local missing files it will download them from the remote server. Note
 because of this, _if a file has been updated remotely it will be ignored_. Also
 it uses PHP to create the file tree list, so files that are untracked by
 SilverStripe are downloaded. This also means that files that don't have public
 permission to be downloaded (e.g. .htaccess) will result in a non-terminating
 warning.



### Installation 

 - Copy \fileassetssync to your folder
 - flush /dev/tasks
 - run the task when it appears
 - create you own yml **mysite/_config/remoteassetssync.yml**:

```
---
Name: RemoteAssetTask
---
RemoteAssetTask:
  target: https://remotedownloaddomain.com/
  key: url_friendly_passprase
  excludedfolders:
    - /static-cache
    - /largefiles
    - /_generated_pdfs
```


### How it works

By running **/dev/tasks/RemoteAssetTask** your browser sends an ajax request 
to your server which will compare its file list against the target computer.

Your browser will then one by one send a request to download each file to your local server.



### Notes

 - The task will download *new* files
 - The data flys over the wire, a secret key is set in the yml file
   but you want it to be done securely use a HTTPS url. 
