# Template Helper for MyBB

A composer package to help you edit MyBB templates locally with your favorite code editor. You can use it to create a local copy of the MyBB templates, edit them, and compile them back to the original format.

## Requirements

The requirements for this package to work are as follows:

- **MyBB:** 1.8 or higher
- **PHP:** 8.2 or higher

## Installation

Install the package using Composer by running:

```shell
composer require tedem/mybb-template-helper --dev
```

Enjoy!
## Usage

Create a new theme from the ACP or use the theme you have already created.

**Attention:** Remember to make a backup before use. For theme development only.

### Download Templates Command:

```shell
./vendor/bin/mybb-template-helper -d "[THEME NAME HERE]"
```

Your theme must have a template set. If not, you can create one through the ACP. First, the core templates will be installed, and then if there are any modifications in your theme, those templates will be updated locally.

The downloaded templates will be listed in the `.temp` folder in the main directory of MyBB.

### Upload Templates Command:

```shell
./vendor/bin/mybb-template-helper -u "[THEME NAME HERE]"
```

The update command will update the templates you have modified. If the modified template does not belong to your theme's template set, it will create a new template for your theme with the same name.

### Upload Specific Templates Command:

```shell
# Only one template
./vendor/bin/mybb-template-helper -u "[THEME NAME HERE]" header

# Many templates
./vendor/bin/mybb-template-helper -u "YOUR THEME NAME" header footer
```

Specifying a custom template will only load the template you have selected. No changes will be made to other templates.

**Note:** You need to run the command after each change. You can use looping software to automate it.

## Contributing

Please do not contribute as the documentation for this project is not complete.

## Versioning

I use [SemVer](https://semver.org/) for versioning.

## Authors

- **Medet Erdal** - _Initial work_ - [tedem](https://github.com/tedem)

## License

[MIT](LICENSE)
