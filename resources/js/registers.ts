import {
  IconActivity,
  IconBook,
  IconBroadcast,
  IconCirclesRelation,
  IconFlag,
  IconHeartHandshake,
  IconLayersLinked,
  IconSunHigh,
  IconSunWind,
  IconTiltShiftFilled,
} from '@tabler/icons-react';

export const domainMeta = {
  emergence: {
    label: 'Emergence',
    description: 'What is becoming structured',
    icon: IconCirclesRelation,
    projection: { label: 'Prominence', icon: IconSunHigh },
    registers: ['activity', 'frontier', 'canon'],
  },
  settlement: {
    label: 'Settlement',
    description: 'How structure is resolving',
    icon: IconLayersLinked,
    projection: { label: 'Turbulence', icon: IconSunWind },
    registers: ['signal', 'stance', 'concord'],
  },
};

export const registerMeta = {
  activity: { label: 'Activity', description: 'What is new', color: 'red', icon: IconActivity },
  frontier: { label: 'Frontier', description: 'What is forming', color: 'green', icon: IconFlag },
  canon: { label: 'Canon', description: 'What persists', color: 'blue', icon: IconBook },
  signal: { label: 'Signal', description: 'What has changed', color: 'yellow', icon: IconBroadcast },
  stance: { label: 'Stance', description: 'Where we stand', color: 'pink', icon: IconTiltShiftFilled },
  concord: { label: 'Concord', description: 'Where we agree', color: 'cyan', icon: IconHeartHandshake },
};

export function metaForDomain(domain) {
  return domainMeta[domain] || domainMeta.emergence;
}

export function metaForRegister(register) {
  return registerMeta[register] || {
    label: register || 'Unclassified',
    description: 'Awaiting classification',
    color: 'gray',
    icon: IconCirclesRelation,
  };
}

export function domainForRegister(register) {
  return domainMeta.settlement.registers.includes(register)
    ? 'settlement'
    : 'emergence';
}
