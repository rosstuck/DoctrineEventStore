### Doctrine Event Store

Proof of concept for retrieving Domain Events raised from Doctrine 2 entities and storing them safely with Broadway as an Event Store.

There's some valid use cases but mostly I wanted to horrify some people.

### Prior Art

- The Unit of Work is a shameless knockoff of [boekkooi/DoctrineEventStoreBundle](https://github.com/boekkooi/DoctrineEventStoreBundle). You should totally check it out.
- Broadway and Doctrine 2, obviously.

### Notes

- Needs some more tests
- Need to add support for auto-incremented entities
- To use it safely, you need to open an EntityManager transaction explicitly around both the EventUoW and Doctrine UoW flush. This could be automated using onFlush/postFlush but there's no onError event to trigger an evenly stacked rollback in the event of an error.
- The EventCollector iterates over the entire identity map rather than just those being updated. This takes a few extra iterations but is more predictable than relying on the change detection.
