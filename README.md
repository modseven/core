<p align="center"><b>Mod(ern)(Ko)seven Framework</b></p>

<p align="center">
  <a href="https://packagist.org/packages/toitzi/modseven"><img src="https://poser.pugx.org/toitzi/modseven/v/stable" /></a>
  <a href="https://packagist.org/packages/toitzi/modseven"><img src="https://poser.pugx.org/toitzi/modseven/license.svg" /></a>
  <a href="https://github.com/toitzi/modseven/issues"><img src="https://img.shields.io/github/issues/toitzi/modseven.svg" /></a>
</p>

## What is this project for?

*This project is in development state and not finished yet*

Initially this was for personal use only. But it happens that there were more and more requests if original "Koseven" would support 
namespaces and PSR. As it does not since it needs to be compatible with "Kohana" this repo was created.

## Current State

__In development__, removing deprecated functions and fix code style.

PSR Standards:

| PSR | Description                 | Status                       |
|-----|-----------------------------|------------------------------|
|  1  | Basic Coding Standard       | Implemented but not verified |
|  3  | Logger Interface            | Implemented and verified     |
|  4  | Autoloading Standard        | Implemented and verified     |
|  6  | Caching Interface           | In Progress                  |
|  7  | HTTP Message Interface      | In Progress                  |
|  11 | Container Interface         | In Review                    |
|  12 | Extended Coding Style Guide | Implemented but not verified |
|  13 | Hypermedia Links            | Not implemented but planned  |
|  14 | Event Dispatcher            | In Review                    |
|  15 | HTTP Handlers               | In Review                    |
|  16 | Simple Cache                | In Progress                  |
|  17 | HTTP Factories              | In Review                    |
|  18 | HTTP Client                 | In Review                    |

*Legend: In Review = check if will be implemented, In Progress = currently beeing worked on*

## When should i use this Project instead of Koseven

If you create a new Application and are familiar with Kohana/Koseven or just love how those frameworks work, chances are high
Modseven will also fit. If you update a legacy Kohana application, Modseven is the wrong choice - use Koseven instead.

But also ask you those questions before choosing:

- Do i need namespaces / PSR - Use Modseven
- Do i want to be up-to date in terms of technology used? - Use Modseven
- Do i need backwards compatibility? - Use Koseven
- Do i need a many 3rd party modules? - Use Koseven

Also check the differences below.

## What are the differences to Koseven?

Although it is quite similar to Koseven there are a few changes:

1. Namespaces - Modseven uses the namespace `KO7` for system files and is completely working with namespaces.
2. Autoloader removed and moved to native composer PSR-4 autoloader.
3. The `bootstrap.php` got moved. It's now called `routes.php` and only contains routes. Configuration is done via the `config/app.php` file.
4. Transparent classes + CSF got removed, they are not needed since namespaces are used.
6. Code formatting

_Note: All patches and features introduced in original "Koseven" will also be patched here._

## Documentation

Modseven documentation is basically the same as Koseven with just a few changes (described above). 

Koseven Documentation can be found at [docs.koseven.ga](https://docs.koseven.ga) which also contains an API browser.

## Will work as drop-in of Kohana / Koseven?

No. Also original modules won't be compatible.

## Contributing

Any help is more than welcome! Just fork this repo and do a PR.

## Special Thanks

Special Thanks to all Contributors and the Community!
