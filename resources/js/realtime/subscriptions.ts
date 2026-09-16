import { getEcho } from './echo';
import type { Invalidation } from './notifications';

type Observer = { invalidated: (event: Invalidation) => void; subscribed: () => void };
type Channel = NonNullable<ReturnType<typeof getEcho>>['connector']['channels'][string];
type Subscription = {
  channel: Channel;
  observers: Set<Observer>;
  invalidated: (event: Invalidation) => void;
  subscribed: () => void;
};

const subscriptions = new Map<string, Subscription>();
const acknowledgement = '.pusher:subscription_succeeded';

/** Share a wire channel until its last observer leaves, removing only our listeners. */
export function subscribeInvalidations(name: string, publicChannel: boolean, observer: Observer): () => void {
  const echo = getEcho();
  if (!echo) return () => {};
  const wireName = publicChannel ? name : `private-${name}`;
  let subscription = subscriptions.get(wireName);
  if (!subscription) {
    const channel = publicChannel ? echo.channel(name) : echo.private(name);
    const observers = new Set<Observer>();
    const invalidated = (event: Invalidation) => observers.forEach(item => item.invalidated(event));
    const subscribed = () => observers.forEach(item => item.subscribed());
    subscription = { channel, observers, invalidated, subscribed };
    subscriptions.set(wireName, subscription);
    // Echo's subscribed() wraps callbacks anonymously. Named listeners let us
    // unbind both events even if Pusher retains a pending, cancelled subscription.
    channel.listen('.invalidation', invalidated).listen(acknowledgement, subscribed);
  }
  const owned = subscription;
  const owner = { ...observer };
  owned.observers.add(owner);
  // A second observer may arrive after the shared channel was acknowledged.
  // Pusher clears subscribed on disconnect and emits a fresh ACK on reconnect.
  if (owned.channel.subscription.subscribed) queueMicrotask(() => {
    if (owned.observers.has(owner) && owned.channel.subscription.subscribed) owner.subscribed();
  });

  return () => {
    if (!owned.observers.delete(owner) || owned.observers.size) return;
    owned.channel.stopListening('.invalidation', owned.invalidated).stopListening(acknowledgement, owned.subscribed);
    subscriptions.delete(wireName);
    // leave() also removes private/presence variants, which may have other owners.
    echo.leaveChannel(wireName);
  };
}
