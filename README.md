cms-core
========

CMS Platform for Hummingbird Lite

## Requirements

- PHP 5.6+
- Hummingbird Lite 3.0.1+ <[vecode.net/hummingbird](https://io.vecode.net/hummingbird/)>
- NORM <[github.com/TheWebChimp/norm](https://github.com/TheWebChimp/norm)>
- Parsedown <[github.com/erusev/parsedown](https://github.com/erusev/parsedown)>
- PasswordHash <[openwall.com/phpass](http://www.openwall.com/phpass/)>
- SimpleImage <[github.com/claviska/SimpleImage](https://github.com/claviska/SimpleImage)>

## Installation

First make sure you have all the required dependencies. If you don't meet a certain one, you'll be warned and the setup wizard will not let you continue until you add all the required files.

For NORM you will need to add the Model class, from this gist: https://gist.github.com/biohzrdmx/db1f81c3f077cf0cfb2a28b9c10ae760.

Include the required files on your `functions.inc.php file`, specifically:

	include $site->baseDir('/external/model.inc.php');
	include $site->baseDir('/external/norm.inc.php');
	include $site->baseDir('/external/crood.inc.php');

	include $site->baseDir('/external/lib/Parsedown.php');
	include $site->baseDir('/external/lib/PasswordHash.php');
	include $site->baseDir('/external/lib/SimpleImage.php');

And finally, include the CMS Platform file:

	include $site->baseDir('/external/cms/cms.inc.php');

Don't forget to create your database and configure it in your Hummingbird instance, [click here](https://docs.vecode.net/hummingbird-v3/tutorials/database) for more details on that.

Then, point your browser to your site url and you should be greeted by the Setup Wizard. Follow all the steps and you instance will be ready to rock.

## Credits

Lead coder: biohzrdmx <[github.com/biohzrdmx](https://github.com/biohzrdmx)>

## License

Copyright © 2018 biohzrdmx

**MIT License for non-commercial use**

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.

**Please quote me for more details on commercial use**