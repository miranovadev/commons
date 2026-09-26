export type GraphRailState = 'open' | 'collapsed';
export type GraphRailAction = 'collapse' | 'open';

export function graphRailReducer(_state: GraphRailState, action: GraphRailAction): GraphRailState {
  return action === 'open' ? 'open' : 'collapsed';
}
