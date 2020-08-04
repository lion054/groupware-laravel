# The App Logic

## The Nodes

### User

Attribute: name, email, password, etc.
Related to: Company (n:1), Department (m:n).

### Company

Attribute: name, since, etc.
Related to: Team (1:n), User (1:n).

### Department

Attribute: name, capacity, etc.
Related to: Company (n:1), User (m:n).

## The Edges

### User-Department

Attribute: position, taken_at, left_at, etc.
The user can have more than one position for its department.
This means multiple edges can exist between user and department.

# The App Structure

## Neo4j

Database is Neo4j that represents the graph data.
All groupware projects need group management and permission process thereof.

## Laravel 5.6

This API server uses "vinelab/neoeloquent" to access the Neo4j database.
NeoEloquent is supported in Laravel 5.6.

### Why NeoEloquent?

NeoEloquent is a knid of Eloquent, so it is suitable for dealing with JWT token and verification thereof.
NeoEloquent contains many miscellaneous classes, so it is more heavy than "GraphAware Neo4j PHP Client".
And it is not obvious for making relationship between nodes.
So NeoEloquent will be used as User class for JWT token.
It is so hard to create the alternative to User class for verification of JWT auth by oneself.
So I will use JWT auth as is. And I will use NeoEloquent for User class, as JWT auth needs the Eloquent. But I will use neo4j-php-client not NeoEloquent in general cases, as neo4j-php-client is lighter than NeoEloquent.

## Customization of Laravel 5.6

### Retrieving Boolean Input Values

The disadvantage of Laravel 5.6 is that it doesn't support "retrieving Boolean Input Values" for request.
But it was solved since Laravel 6.x.
This will be fixed by adding customized Reqeust class.
