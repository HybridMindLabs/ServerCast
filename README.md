# ServerCast

Send a message from the HybridCore panel to your game servers — one, several,
or all at once — and watch it get delivered in-game. It works with every game
through the bridge, because the delivery is a normalised `broadcast:` command
that each server plugin translates into its own in-game chat.

## How it works

1. In **Admin → Servers**, compose a message and choose the target servers.
2. ServerCast queues a `broadcast:<message>` bridge command for each server.
3. The plugin polls the bridge, prints the message in-game, and acknowledges.
4. The panel shows per-server delivery status — queued → sent → confirmed.

Only servers with a bridge token can receive broadcasts; the rest are shown
disabled with a hint.

## Permissions

- `servercast.view` — see the page and history
- `servercast.send` — send broadcasts

## Plugin support

The game-server plugin must handle a command of the form `broadcast:<text>` by
printing `<text>` to in-game chat. The official HybridCore bridge plugins do
this out of the box.
