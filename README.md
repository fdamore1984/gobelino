# Gobelino 🚀

**Gobelino** is a lightweight, super-simplified web panel designed for fast, hassle-free remote management of Android and iOS/iPadOS devices.

Built for speed and simplicity, it cuts through the complexity of traditional Enterprise Mobility Management (EMM) platforms, allowing you to configure policies and deploy apps in just a few clicks.

## Key Features

* **Cross-Platform Support**: Manage both Android and Apple (iOS/iPadOS) devices from a single unified interface.
* **Rapid Setup**: Streamlined onboarding and device enrollment with minimal configuration steps.
* **Quick Policy Management**: Apply security and operational policies instantly without deep technical overhead.
* **Effortless App Deployment**: Push, update, and manage applications remotely with absolute ease.

## Android device management

Android devices are managed through our own agent APK (see
`android-agent/`), not through Android Enterprise / the Android
Management API. The APK becomes **Device Owner** via Android's native
QR provisioning flow at setup — no Google EMM binding required — and
talks to the backend via periodic **polling** (no FCM). This lets a
company self-register and start managing devices immediately, without
any bureaucratic enterprise verification step.

See `android-agent/README.md` for build, signing and provisioning
instructions.

* to be updated...
