# Planar
## A super simple flat file json database / model

Throughout this readme, 'document' and 'collection' are used in the MongoDb sense.

Planar is a very basic flat file json database / model solution.

Planar is simple, fast, super-flexible, and probably very brittle. It's useful for small projects, where you have a relatively simple data structure, you don't need 1000s of documents and you only have a small amount of users with edit permissions.

It probably won't scale well, there's no collision detection or record locking, and will most likely slow to a crawl once your collections get really large, but I've used it in production apps with 100s of documents and had zero issues so far.

Planar creates json collections on the fly as needed, everything gets json encoded and stored in flat files. It is ORM-like, even though that's pretty much an irrelevant term since this is all json anyway, but you can do CRUD and simple queries on a 'model'-like object.

It backs up every change using diffs, although there isn't any undo quite yet.

## Disclaimer
It's still pretty alpha, could break at any time, and I might make backwards incompatible changes without any warning. Don't use it for anything too business critical. You have been warned.

## Install

`composer require moussaclarke\planar`

## Usage

### Instantiation
At its simplest, you just extend the class to create your model/collection. It will use the class name (plural makes sense here) as the json collection name.

```
use MoussaClarke\Planar;

class Widgets extends Planar
{
    
}
```

You can then instantiate passing in a data folder location. The json and backup diff files will be stored in the folder you specify. If the json file doesn't exist yet, it will be created.

```
$widgets  = new Widgets('path/to/data/folder');
```

### Schema

You can add a schema for the document if you like - Planar won't do anything to enforce it, and each document could in fact have completely different elements - but it might be useful elsewhere in your app to get a default instance of the data if you're trying to maintain a more rigid model structure, i.e. as a kind of configuration info. To do this, just over-ride the `$schema` property at the top of your model class.

```
protected $schema   = [
    'name' => ''
    'price' => '',
    'weight' => '',
    'subwidgets' => [],
    'invoicedate' => date ('Y-m-d')
];
```

Your initial property defaults would usually be an empty string, but you could also specify arrays or defaults or generated data like dates if you like. You can then grab your schema like this:

```
$schema = $widgets->getSchema();
```

It's up to you to then `add` or `set` it back to the collection once you've loaded the array with data.

### Creating & updating

You can create a document with `add`. Just pass in an array of data (which simply gets json encoded), it will return the unique id of the new document.

```
$data = [
    'name' => 'foobar',
    'price' => 5,
    'weight' => 10,
    'subwidget' => ['foo' => 'bar', 'bar' => 'foo']
];
$id = $widgets->add($data);
```

You don't need to worry about adding unique id or timestamp fields, those will be created and updated automatically. `id` is simply a `uniqid()`, and `created` and `modified` are unix timestamps. Those three property names are therefore reserved, so try not to have those in your data/schema.

If you know the id, you can replace a whole document with `set`.

```
$widgets->set('57d1d2fc97aee', $data);
```

### Finding & Searching

Planar has various ways of finding and searching records, although doesn't really support any particularly sophisticated queries. `find` returns an array of documents where the named property has a particular value.

```
$result = $widgets->find('price', 5);
```

`first` returns the first document it finds where the named property has a particular value.

```
$result = $widgets->first('id', '57d1d2fc97aee');
```

`all` returns the whole collection as an array, so you could perform more complicated queries on that.

```
$result = $widgets->all();
```

You can also sort the `all` results in ascending order by passing in a property name to sort by.

```
$result = $widgets->all('price');
```

`search` allows you to search for a term or phrase throughout the whole collection. It returns an array of documents where any property contains the value, and is case insensitive.

```
$widgets->search('foo')
```

### Deleting

You can `delete` a document if you know the id.

```
$widgets->delete('57d1d2fc97aee');
```

### Failing

Most of the methods above will return `false` on fail so you can check if your call was successful.

```
if ($widgets->delete('57d1d2fc97aee')) {
    echo 'Document succesfully deleted';
} else {
    echo 'Document not found';
}
```

Only the `add` method never returns `false`. As repeatedly mentioned, it will essentially `json_encode` any array you feed it, so doesn't really 'fail' as such, unless you don't send it an array. This has its own risks, so make sure the data you feed it isn't too weird!
 
### Persistence

You might sometimes want non-persistent collections, for example you might want to scaffold out a sub-model for ajax-y UI purposes, but it doesn't make sense for the data to persist in its own collection since it ultimately gets saved to the parent model/collection. For this use case, just over-ride the class `$persists` property and it will garbage collect once a day.

```
protected $persists = false;
```

### Todo
* Retrieve historical state/undo
* Errors/Exceptions
* More granular set method, i.e. just one property rather than the whole document.
* Some better query/search options, e.g. Fuzzy search / regex
* Docblocks
* Slightly more verbose documentation with Daux.io, with clearer explanations and some longer examples
* Tests

### Maintained
By [Moussa Clarke](https://github.com/moussaclarke/)

### Contribute
Feel free to submit bug reports, suggestions and pull requests, although can't guarantee I've got enough time to pay much attention to it. Alternatively just fork it and make your own thing.

### License
MIT

