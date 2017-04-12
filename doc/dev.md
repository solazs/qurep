# Developer documentation

This document assumes the reader has a comprehensive understanding of Symfony, Doctrine and developing Symfony applications.

## Adding QuReP to your app

### Prerequisites, conditions

**QuReP requires all relations to be bi-directional between your Entities.**
 
The Bundle was designed to work with Doctrine and JMS Serializer, thus it relies heavily on these libraries.

#### 1. Install QuReP

`php composer.phar require solazs/qurep:dev-master`

#### 2. Add the QuReP Bundle to `app/AppKernel.php`

Composer may do this automatically, but if not, insert the following lines into the `$bundles` array in
`app/AppKernel.php`:

```php
class AppKernel extends Kernel
{
    public function registerBundles()
    {
        $bundles = [
            // ...
            new JMS\SerializerBundle\JMSSerializerBundle(),
            new Solazs\QuReP\ApiBundle\QuRePApiBundle()
        ];

        if (in_array($this->getEnvironment(), ['dev', 'test'], true)) {
            // ...
        }
        
    // ...
}
```

#### 3. Create your Doctrine Entities

(if you haven't already)

#### 4. Add QuReP configuration

`app/config/config.yml`:
```yaml
qurep_api:
    entities: "%qurep_entities%"
```

`app/config/parameters.yml`:
```yaml
qurep_entities:
    - entity_name: users
      class: Your\Bundle\Entity\User
    - entity_name: comments
      class: Your\Bundle\Entity\Comment
```

`entity_name` is a string used in the route of the resource

`class` is the Entity class of the resource


#### 5. Annotate Entities with QuReP's Field Annotation

All properties of all Entities must be annotated with `Solazs\QuReP\ApiBundle\Annotations\Entity\Field` annotation.

Only properties annotated with `Field` will be taken account by QuReP.

**Parameters of the `Field` annotation**

* `type` string, Symfony Form Field class name. 
[See the docs for more](http://symfony.com/doc/current/reference/forms/types.html)

* `options` array, options array for the Form Fields

* `label` string, defaults to the property name. Can be used to override property name on the REST API

**Note:** only properties with a vaild `type` will be allowed to be POST-ed to the API with the exclusion of relations
(that is, properties with ManyToMany, ManyToOne, OneToMany, OneToOne annotations), whose type will always be EntityType.

For an example, see [the test app](https://github.com/solazs/qurep-testing/tree/master/src/QuRePTestBundle/Entity)

#### 6. Add routing

`app/config/routing.yml`:
```yaml
qurep_api:
    resource: "@QuRePTestBundle/Controller/"
    type:     annotation
    prefix:   /api
```

Should you want to expand the QuReP-provided API, just add the routes before the `/api` route:

```yaml
my_custom_api:
    resource: "@MyApiBundle/Controller/"
    type:     annotation
    prefix:   /api/specific
qurep_api:
    resource: "@QuRePTestBundle/Controller/"
    type:     annotation
    prefix:   /api
```

That's all, you now have a fully configured and function REST API.

**IMPORTANT**: don't forget to secure your API, or everyone will be able to use it.
QuReP provides no authentication as it would narrow usability severely, although some kind of 
authorization **is** planned.

Now that you're done here, you might as well check out the [API docs](rest.md)
