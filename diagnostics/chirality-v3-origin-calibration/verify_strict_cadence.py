#!/usr/bin/env python3
import math

# Canonical direct pre-Mediate route values reproduced from production WL/Cardinal semantics.
pre_mediate = {
    1: -0.2642977396044841,
    2: -0.4742165769367914,
    3: -0.4413933000758162,
    4: -0.5350935285869912,
}
chi_max = 0.25
assert all(c + chi_max < 0 for c in pre_mediate.values())

topology_upper = 2 / math.sqrt(3) - 1
post = {}
for depth in range(5, 8):
    cardinal = 2 / (depth + 1) - 1
    correspondence = (topology_upper + cardinal) / 2
    post[depth] = correspondence
    assert correspondence + chi_max < 0

head_upper = post[7] + chi_max
origin_positive = 0.5 / (1 + 7) ** 2
numerator_upper = head_upper + origin_positive
assert numerator_upper < 0

print({
    'pre_mediate_combined_upper': {d: c + chi_max for d,c in pre_mediate.items()},
    'post_mediate_correspondence_upper': post,
    'head_combined_upper': head_upper,
    'origin_weighted_combined': origin_positive,
    'attenuation_numerator_upper_ignoring_other_negative_ancestors': numerator_upper,
    'strict_homology_first_crossover_reachable': False,
})
