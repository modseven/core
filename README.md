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

PSR Standards to expect soon:

| PSR | Description                 | Status                       |
|-----|-----------------------------|------------------------------|
|  1  | Basic Coding Standard       | Implemented                  |
|  3  | Logger Interface            | Implemented                  |
|  4  | Autoloading Standard        | Implemented                  |
|  6  | Caching Interface           | In Progress                  |
|  7  | HTTP Message Interface      | Not implemented but planned  |
|  12 | Extended Coding Style Guide | Implemented                  |
|  13 | Hypermedia Links            | Not implemented but planned  |
|  15 | HTTP Handlers               | Not implemented but planned  |
|  16 | Simple Cache                | In Progress                  |
|  17 | HTTP Factories              | Not implemented but planned  |
|  18 | HTTP Client                 | Not implemented but planned  |

*PSR Standards that will not make it into Modseven:*

*PSR 11 (Container Interface) - No built in feature of Modseven*

*PSR 14 (Event Dispatcher) - No built in feature of Modseven*

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

1. Namespaces - Modseven uses the namespace `Modseven` for system files and is completely working with namespaces.
2. Autoloader removed and moved to native composer PSR-4 autoloader.
3. The `bootstrap.php` got moved. It's now called `routes.php` and only contains routes. Configuration is done via the `config/app.php` file.
4. Transparent classes got removed, they are not needed since namespaces are used.
5. The `Cache` and `Encrypt` Module are now core classes
6. Koseven Deprecated Classes and functions got completley removed since we do not need them
7. Code formatting, small bug fixes, micro optimizations, etc..
8. CSF for configuration and templates is currently under review as i think of how i will implement those

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
