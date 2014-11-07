# Upgrading

This document will be updated to list important BC breaks and behavorial changes.

## Upgrading to 2.0.0

 - Method `M6Web\Component\Redis::get()`, now, return `null` if required value doesn't exist like Redis do