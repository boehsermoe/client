# client

The client binary which sends the data to the server and creates the output from the API response.

## Usage

```sh
composer require --dev fwcc/client
```

Add the `.fwcc` files, for example `main.fwcc`:

```json
{
    "css" : [
        "../../src/scss/main.scss"
    ],
    "js" : [
        "js/jquery.js",
        "js/app.js",
        "js/datepicker.js"
    ],
    "options": {
        "compress" : true,
        "maps" : true
    }
}
```


Run inside current directory and all sub directories:

```sh
./vendor/bin/fwcc watch
```

Listen inside a certain folder:

```sh
./vendor/bin/fwcc watch resources/
````

Run only once

```sh
./vendor/bin/fwcc compile
```