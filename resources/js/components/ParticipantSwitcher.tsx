import { useEffect, useRef, useState } from 'react';
import { Alert, Badge, Box, Button, Drawer, Group, Loader, Stack, Text } from '@mantine/core';
import { useNavigate } from 'react-router-dom';
import { messageFrom } from '../errors';
import { participantIsWaiting, participantPath, switchableParticipants } from '../participantSelection';
import { useEnterCycleMutation, useGetParticipantsQuery, useGetParticipationQuery, useGetSessionQuery } from '../services/commonsApi';
import type { OriginInput, Participant } from '../types';
import { ParticipantAvatar } from './ParticipantIdentity';
import { OriginCycleForm } from './OriginCycleForm';
import { CycleEntryControl } from './CycleEntryControl';

export function ParticipantSwitcher({ slug, participant, opened, onClose }: {
  slug: string; participant: Participant; opened: boolean; onClose: () => void;
}) {
  const navigate = useNavigate();
  const { data: all = [], isFetching, error } = useGetParticipantsQuery(slug, { skip: !opened, refetchOnMountOrArgChange: true });
  const { data: user } = useGetSessionQuery(undefined, { skip: !opened });
  const [checking, setChecking] = useState<Participant | null>(null);
  const [originTarget, setOriginTarget] = useState<Participant | null>(null);
  const [enterCycle, entry] = useEnterCycleMutation();
  const submitting = useRef(false);
  const mounted = useRef(true);
  useEffect(() => { mounted.current = true; return () => { mounted.current = false; }; }, []);
  useEffect(() => { if (!opened) setChecking(null); }, [opened]);
  const participants = switchableParticipants(all, user?.has_system_access);
  function select(target: Participant) { onClose(); navigate(participantPath(slug, target.id)); }
  async function enter(target: Participant, origin?: OriginInput) {
    if (submitting.current) return;
    submitting.current = true;
    try {
      await enterCycle({ slug, participantId: target.id, ...(origin ? { origin } : {}) }).unwrap();
      if (mounted.current) { setOriginTarget(null); select(target); }
    } catch { /* Keep the current Drawer/form open and show the server's reason. */ }
    finally { submitting.current = false; }
  }
  return <>
    <Drawer opened={opened} onClose={onClose} title="Participants" position="right" size="md">
      <Stack>
        <Group wrap="nowrap"><ParticipantAvatar callsign={participant.callsign} size={40} /><Box miw={0}>
          <Text fw={700}>{participant.callsign}</Text><Text size="sm" c="dimmed">{participant.designation || 'Participant'}</Text>
        </Box></Group>
        <Text size="xs" c="dimmed">Switch your point of participation in this Substrate.</Text>
        {isFetching && <Loader size="sm" aria-label="Loading Participants" />}
        {error && <Alert color="red">{messageFrom(error, 'Participants could not be loaded.')}</Alert>}
        {participants.map(target => <ParticipantSwitchRow key={target.id} participant={target} current={target.id === participant.id} onSelect={() => select(target)}
          onCheckEntry={() => { entry.reset(); setChecking(target); }} checking={checking?.id === target.id} busy={entry.isLoading} />)}
        {checking && <Box className="participant-switcher__entry" key={checking.id}>
          <Text size="sm" fw={650} mb="xs">Cycle entry · {checking.callsign}</Text>
          <ParticipantEntry slug={slug} participant={checking} loading={entry.isLoading} error={entry.error}
            onEnter={() => void enter(checking)} onOrigin={() => { entry.reset(); setOriginTarget(checking); onClose(); }} />
        </Box>}
        {!isFetching && !error && !participants.length && <Text size="sm" c="dimmed">No available Participants were found.</Text>}
      </Stack>
    </Drawer>
    <OriginCycleForm opened={Boolean(originTarget)} loading={entry.isLoading} error={entry.error} onClose={() => setOriginTarget(null)}
      onSubmit={origin => originTarget ? enter(originTarget, origin) : Promise.resolve()} />
  </>;
}

export function ParticipantSwitchRow({ participant, current, checking, busy = false, onSelect, onCheckEntry }: {
  participant: Participant; current: boolean; checking?: boolean; busy?: boolean; onSelect: () => void; onCheckEntry: () => void;
}) {
  const waiting = participantIsWaiting(participant);
  return <Box className="participant-switcher__row" data-current={current}>
    <Group wrap="nowrap" align="flex-start"><ParticipantAvatar callsign={participant.callsign} /><Box miw={0} flex={1}>
      <Group gap="xs"><Text size="sm" fw={650}>{participant.callsign}</Text>{current && <Badge size="xs" color="primary" variant="light">Current</Badge>}</Group>
      <Text size="xs" c="dimmed">{participant.designation || 'Participant'}</Text>
      <Text size="xs" c="dimmed" mt={3}>{waiting ? 'Waiting to enter a Cycle' : `Cycle ${participant.position?.cycle_sequence} · Phase ${participant.position?.phase?.number ?? 'in progress'}`}</Text>
    </Box></Group>
    <Group gap="xs" mt="xs">
      <Button size="compact-xs" variant="light" color={current ? 'gray' : 'primary'} onClick={onSelect} disabled={busy} aria-label={`Switch to ${participant.callsign}`}>{current ? 'Return to Participant' : 'Switch Participant'}</Button>
      {waiting && <Button size="compact-xs" variant="subtle" color="gray" onClick={onCheckEntry} disabled={busy} aria-expanded={checking} aria-label={`Check Cycle entry for ${participant.callsign}`}>Check Cycle entry</Button>}
    </Group>
  </Box>;
}
function ParticipantEntry({ slug, participant, loading, error, onEnter, onOrigin }: {
  slug: string; participant: Participant; loading: boolean; error?: unknown; onEnter: () => void; onOrigin: () => void;
}) {
  const state = useGetParticipationQuery({ slug, participantId: participant.id }, { refetchOnMountOrArgChange: true });
  if (state.isFetching) return <Loader size="sm" aria-label="Checking Cycle entry" />;
  if (state.error) return <Alert color="red">{messageFrom(state.error, 'Cycle entry could not be checked.')}</Alert>;
  if (!state.currentData) return null;
  return <Stack gap="xs">
    {Boolean(error) && <Alert color="red">{messageFrom(error, 'The Cycle could not be entered.')}</Alert>}
    <CycleEntryControl participation={state.currentData} loading={loading} onEnter={onEnter} onOrigin={onOrigin} />
  </Stack>;
}
