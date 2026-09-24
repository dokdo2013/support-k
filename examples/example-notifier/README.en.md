[한국어](README.md) | English

# Example notifier

This synthetic extension shows the smallest notification-channel integration.
Its manifest declares compatibility and permissions, while the entrypoint
registers a channel through the public `ExtensionRegistrar` contract. The
example performs no network or filesystem I/O.

An extension is trusted PHP code running with the application process rights.
Manifest permissions help review and gate core APIs; they are not a sandbox.
