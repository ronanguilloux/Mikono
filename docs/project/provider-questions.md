# Pre-sales email to VPS providers

**Last updated:** 2026-09-04

The five questions in
[`hosting-plan.md`](hosting-plan.md#five-questions-to-ask-before-paying),
written as one email to send unchanged to every candidate. Send it to
all four and compare the replies side by side — the reply is a sample of
the support you would be buying, so how fast and how specifically they
answer is part of the data, not a preamble to it.

Do not send it from the app or automate it. It is four emails, once.

## Where to send it

Use each provider's own contact form or the sales address on their site
rather than a guessed mailbox:

- **Lineserve** — <https://www.lineserve.co.ke/>
- **Truehost Kenya** — <https://truehost.co.ke/>
- **Hostnali** — <https://hostnali.co.ke/>
- **HostPinnacle** — <https://www.hostpinnacle.co.ke/>

## The email

> **Subject:** Pre-sales questions — Linux KVM VPS for a Docker workload
>
> Hello,
>
> I am evaluating a VPS for a small non-profit web application used by
> staff in Nairobi and Mombasa. It runs as a single Docker container
> that terminates TLS itself, so it needs the whole machine's ports 80
> and 443 — there is no cPanel or other web server in front of it. I am
> looking at your [PLAN NAME] plan at [PRICE].
>
> Five questions before I buy, if you would be kind enough to answer
> them in writing:
>
> 1. **Which datacentre is this plan physically hosted in?** I need the
>    facility or city, not the billing country — data residency is a
>    requirement for this project, not a preference.
> 2. **Is the virtualization KVM, or OpenVZ/LXC?** Docker needs KVM.
> 3. **Is the server delivered unmanaged, with full root and no control
>    panel preinstalled?** Anything holding ports 80 or 443 on delivery
>    is a problem I would rather know about now.
> 4. **Is UDP traffic on port 443 permitted, inbound and outbound?**
>    The application serves HTTP/3, which needs it. Many hosts filter
>    UDP by default.
> 5. **Do you offer snapshots — and can a customer restore one
>    themselves from the control panel?** If so, roughly how long does a
>    restore of a 20 GB volume take?
>
> Two smaller ones, if they are not already on the plan's page: **is
> IPv6 included**, and **is the CPU x86-64 rather than ARM?** The
> application image is built for x86-64 only.

The x86-64 question looks pedantic and is not: `hosting-plan.md` §2
publishes an amd64-only image, so an arm64 instance chosen on price will
simply fail to start the container.
>
> Thank you,
> [NAME]

## Recording the answers

Put the replies straight into
[`hosting-plan.md`](hosting-plan.md)'s candidates table as they arrive —
that file is the one an ADR will be written from. A provider that does
not answer question 1 has answered question 1.
