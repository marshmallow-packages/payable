# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.11.0] - 2026-06-23

### Added

- `Provider::prepareCustomPayment()` and an `is_custom` flag on the payable `startPayment()` trait method. This creates a payment record for an externally handled (custom) provider flow, without running the standard provider checkout. Backported from the `hotfix/stripe` line so consumers can depend on a tagged 2.x release instead of a dev branch.

## [1.0.0] - 2021-01-26
