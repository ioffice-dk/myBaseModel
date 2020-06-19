# myBaseModel
With myBaseModel you can extends your own models and get some extra query functions. It is meant to be simple and NOT laravel illuminate. :)
I used to use it first in a project to quickly getting a "working" model to show if a framework are not choosen yet and in small scripts.

### Using
Setup .io.json with the section

    {
        "mysql": {
            "default": {
                "host": "localhost",
                "user": "",
                "pass": "",
                "base": "",
                "port": "3306"
            }
        }
    }

```php
namespace App\Model;

Class Users extends \Io\Model\Base\myBaseModel
{
    protected $table = 'user';
}
```

### Single call :
    $User = Users::new()
    $User = Users::id({row id})
    $User = Users::sql({query})
    
### Multi call
    ::where({field}, {Operator}, {value})
    ::whereOr({field}, {Operator}, {value})
    ::fields([{field name}])
    ::sort({field name DESC|ASC})

    ex. $Users = Users::where('active', 'IS NOT NULL')->fields('name')->sort('name DESC');
    
### Resource

    $Users->count()
    $Users->next()
    $Users->getArray()    

###  Result

    $User = $Users->next()

    $User->{fieldname}
    $User->isReady()
    $User->getId()
    $User->getObject()
    
    $Status = $User->save()
    $Status = $User->delete()

    ex. $User = $Users->next()
    if ($User)
    {
        return $User->name;
    }
    
### Saving | Deleting
    $Status->success
    $Status->updated
    $Status->created
    $Status->matched
    $Status->warning
    $Status->error


    
### Install
```sh
$ composer require iofficedk/mybasemodel
```

