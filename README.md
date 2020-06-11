# Laravel VueTable

A Laravel package for handling requests from [@kritpiko/vue-table](https://github.com/KriptikoCreativeStudio/vue-table) component.


## Installation

Add the package to your Laravel app using composer

```
composer require kriptiko/laravel-vue-table
```


## Usage

Here's an example of a paginated Query Builder result:

```
use Kriptiko\VueTable\VueTableRequest;

class PostController extends Controller
{
    
    public function index()
    {
        $vtr = new VueTableRequest(Post::query());

        return $vtr->paginated();
    }

    ...
```


---


## License

**kriptiko/laravel-vue-table** is open-sourced software licensed under the [MIT license](https://github.com/KriptikoCreativeStudio/laravel-vue-table/blob/master/LICENSE).


## About Kriptiko

[Kriptiko](https://www.kriptiko.com) is a Creative Studio specialized in web development based in Matosinhos, Portugal.
