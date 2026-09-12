import { IconArrowFork, IconGitBranch, IconGitCommit, IconGitCompare, IconGitFork, IconGitMerge, IconGitPullRequest } from '@tabler/icons-react';

const operations = {
  variant: IconGitBranch, disperse: IconGitFork, mediate: IconGitMerge,
  reconcile: IconGitMerge, reclassify: IconGitCompare, revise: IconGitPullRequest,
  commit: IconGitCommit, bias: IconArrowFork,
};
export function OperationIcon({ operation, size = 15 }: { operation: string; size?: number }) {
  const name = operation.replace('proposition.', '').toLowerCase();
  const Icon = operations[name as keyof typeof operations] || IconGitBranch;
  return <Icon size={size} aria-hidden="true" className="operation-icon" data-operation={name} />;
}
