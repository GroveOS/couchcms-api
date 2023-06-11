# CouchCMS API
A simple API for CouchCMS -- currently in beta, but ready for testing. The API accepts form-urlencoded GET and POST requests, and returns JSON responses. The API is currently in beta, so we're still working on the documentation. Please try it out and reach out with any questions or suggestions to support@groveos.com.


- - -


## Setup
First, you'll need to create a random string to serve as your API key for your project and add it to your `couch/config.php` file. Many password managers and online services can do this for you, or you can use the following command in your terminal to generate one:
```sh
# generate a random string
openssl rand -base64 32
```

Then, add it to your `couch/config.php` file:
```php
// couch/config.php
define( 'K_API_KEY', 'Rwl+LUk3JAxnPHgvI62P/avGlfppgfU136IvG7cBKLw=' );
```

Finally, the API uses the CouchCMS routes addon, so be sure it's activated in your `couch/addons/kfunctions.php` file.
```php
// couch/addons/kfunctions.php
require_once( K_COUCH_DIR.'addons/routes/routes.php' );
```


- - -


## Usage
Two use cases:
1. via web browser (via logged-in admin authentication)
2. via command line tool or remote server (via API authentication)

**IMP.** The API key gives anyone who has it access to your CouchCMS project data. Do not expose your API key to the public (such as in client-facing javascript).


- - -


## Seven Enpoints:
- **v0/index/** (API documentation)
- **v0/{:template}/** (list)
- **v0/{:template}/doc/** (template documentation)
- **v0/{:template}/create/** (create)
- **v0/{:template}/&id={:id}** (single record)
- **v0/{:template}/edit/&id={:id}** (edit record)
- **v0/{:template}/delete/&id={:id}** (delete record)


- - -


## Examples
More detailed documentation is coming, but here's a collection of examples to learn by osmosis in the meantime. *Note*: for now, all API requests require authentication (either as logged-in admin or with api_key POST value), but we're working on adding a way to expose certain templates and records publicly, via your own custom configuration.


### via Command Line
To keep the command line clean, we're piping results to the command line tool 'jq' for nice JSON formatting, and we're using '-s' to silence commanline output during the request itself.

```bash
!#/bin/bash

base_url=my-site.com/api.php?q=v0
api_key=Rwl+LUk3JAxnPHgvI62P/avGlfppgfU136IvG7cBKLw=

####
### GET requests
#####

# list people
curl -s -d api_key=$api_key "$base_url/people/" | jq

# list people with a name of John
curl -s -d api_key=$api_key "$base_url/people/&where=name.eq.John" | jq

# list people with a name of John, but only return their name and id
curl -s -d api_key=$api_key "$base_url/people/&where=name.eq.John&fields=name,id" | jq

# list people with a name of John, but only return their name and id, and limit to 1 result
curl -s -d api_key=$api_key "$base_url/people/&where=name.eq.John&fields=name,id&limit=1" | jq


####
### POST requests
#####

# create a new person
curl -s -d api_key=$api_key -d name=John "$base_url/people/create/" | jq

# edit a person
curl -s -d api_key=$api_key -d name=James "$base_url/people/edit/&id=1" | jq

# delete a person
curl -s -d api_key=$api_key "$base_url/people/delete/&id=1" | jq
```


### via HTML + javascript
As a logged in user, we can send regular HTML form requests to the API. Just make sure you're logged in as an admin.


#### Vanilla JS
```html
<form id="myForm">
  <!-- Form fields here -->
  <input type="text" name="name" id="name">
  <input type="submit" value="Submit">
</form>

<script>
  const form = document.getElementById('myForm');
  form.addEventListener('submit', function(event) {
	event.preventDefault();
	fetch('my-site.com/api.php?q=v0/people/create/', {
	  method: 'POST',
	  body: new FormData(event.target)
	})
	.then(response => response.json())
	.then(data => {
		console.log(data);
		// do something with the response data
	})
  });
</script>
```


#### AlpineJS
```html
<div x-data="myForm()">
  <form x-on:submit.prevent="submitForm">
	<input type="text" name="name" id="name" x-model="name">
	<input type="submit" value="Submit">
  </form>
</div>

<script>
	function myForm() {
	  return {
		name: '',
		submitForm() {
		  fetch('my-site.com/api.php?q=v0/people/create/', {
			method: 'POST',
			body: new FormData(event.target)
		  })
		  .then(response => response.json())
		  .then(data => {
		  	console.log(data);
			// do something with the response data
		  })
		}
	  }
	}
</script>
```


#### jQuery
```html
<form id="myForm">
  <!-- Form fields here -->
  <input type="text" name="name" id="name">
  <input type="submit" value="Submit">
</form>

<script>
  $('#myForm').submit(function(event) {
	event.preventDefault();
	$.ajax({
	  url: 'my-site.com/api.php?q=v0/people/create/',
	  method: 'POST',
	  data: $(this).serialize(),
	  success: function(data) {
	  	console.log(data);
		// do something with the response data
	  }
	});
  });
</script>

```


- - -


## More to come
More documentation to come, but that should get you started! Any questions, please reach out at support@groveos.com.