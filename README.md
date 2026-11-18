# Bisklet | Open Source Bike-Sharing Technology

Bisklet is an open-source mobility software project developed in Ethiopia for bike-sharing operators and developers.

This release combines a Flutter rider app, Laravel API, and Go TCP gateway for smart bike locks. The source has been tested against real hardware. This new open-source project does not claim external adoption, a deployed public fleet, or community contributors yet.

## Components

- `frontend/`: Flutter rider application and maps.
- `backend/`: Laravel ride operations, authentication and payment integration.
- `gateway/`: Go TCP gateway and security controls for provisioned bike locks.

## Safety and licensing

Never use production credentials or real customer/device data in demos. Operators must independently review local rules and physical security controls before deployment. This is a clean public history and intentionally excludes private repositories' Git history.

Original Bisklet code: Apache License 2.0. Dependencies remain under their own licenses. See `THIRD_PARTY.md` and `SECURITY.md`.

## Status

The public release is being assembled and verified. Check the Actions tab for CI results before using this repository.
