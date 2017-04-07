# REST API documentation

Throughout the API docs, a theoretical entity, `User` (`users` on the AI) will be used in the examples.


## Format

All data is expected and sent in JSON format, and is returned in a JSON object:

```json
{
  "meta": {},
  "data": {}
}
```

`meta` is still WIP, and will contain information about the call (links for navigation, paging information,
expand and filter information)

`data` contains an array of Entities in case of collections or a JSON representation of an Entity.

## Actions

Method | Single resource (`/users/1`) | Collection (`/users`)
-------|------------------------------|-----------------------
GET    | get single user with ID 1    | get collection of users
POST   | update user with ID 1        | create a new user
DELETE | delete user with ID 1        | delete users listed in body

The newly/created or modified resource is sent back to the client in response to any successful POST call.

DELETE /users expects an array of ID-s to delete from the collection.

## Filtering collections

Filtering collections is an extremely useful feature of QuReP.
It is done by providing filter expressions as query parameters.

A valid filter expression consists of a field name, an operation and an
optional argument: `/users?filter=name,like,admin`

Valid operators are: `isnull`, `isnotnull`, `eq`, `neq`, `gt`, `gte`, `lt`, `lte`, `like`, `notlike`.

Filter expressions may be combined to form logical expressions. Examine the following filter expression:

`/users?filter=name,like,admin&filter=age,gte,18;age,lt,60`

The first `filter` expression states that we are looking for the user whose name contains the "admin" string.
Further `filter` expressions are in a logical OR relation with each other. The second expression contains an AND
relation: we are looking for users aged between 18 and 60.

Filtering works through relations too: `/users?filter=parent.name,like,admin` will select only the children of the admin

## Expanding the data

In a regular GET call, Entity relations will not be serialized. E.g. the parent of a User (which is a ManyToOne
relation) will not be included. 

To expand these relations,`expand` query option must be provided: `/users?expand=parent`.

Expanding also works through relations: `/users?expand=parent.posts` will expand the User's parent and their posts.

## Paging collections

Paging may be done by specifying `limit` and `offset` query parameters.

`limit` specifies the number to start listing from, defaults to 0.

`offset` specifies the number of resources to return, defaults to 25.

## Bulk POST

**IMPORTANT NOTE:** This feature is a work-in-progress.

There is a special method for updating and/or creating multiple resources in a single call. 

`POST /users/bulk` expects an array of entity representations. Any entity with an ID that is found in the database
will be updated, the others created (regardless of ID), with the whole array returned on success.
