# Putting a shared cache in front of the site

The module writes everything a proxy needs to cache the pages of the site and to
drop the right ones when they change. It does not talk to a proxy itself, and
this page explains what is missing between the two.

## Why nothing is cached out of the box

Thelia opens a session on every front-office request and sets `PHPSESSID` on the
way out, on plain anonymous requests included. A response that sets a cookie
must never be marked `public`: a proxy that stored one would serve the session
of the first visitor to everybody after them.

So the module marks a page `public` only when the response carries no cookie of
its own, which on a stock Thelia never happens. Setting `http_cache_ttl` in the
settings changes nothing until the proxy is configured to drop that cookie on
the addresses it is allowed to cache. That configuration is below.

Two headers are written in every case, cached or not:

```
Cache-Tag: cms-page-12, cms-menu, cms-settings
Surrogate-Key: cms-page-12 cms-menu cms-settings
```

The two say the same thing under the two names proxies read: commas for
Cloudflare and Akamai, spaces for Fastly and for the Varnish `xkey` module.

## Varnish

The part that matters is the first block: on the addresses of the public site,
the request loses its session cookie on the way in and the response loses its
`Set-Cookie` on the way out. Everything else is a normal Thelia setup.

Adjust the list of public paths to the site. Anything an editor or a customer
signs into stays out of it: the back office, the account pages, the cart, the
checkout. So does any address that answers differently to two visitors.

```vcl
vcl 4.1;

import xkey;   # tag-based invalidation; drop it and use a ban if unavailable

backend default {
    .host = "127.0.0.1";
    .port = "8080";
}

acl purgers {
    "localhost";
    # the address the site purges from
}

sub is_public_page {
    # Addresses served the same way to everybody. Everything else keeps its
    # session and is never cached.
    set req.http.X-Public = "0";

    if (req.method == "GET" || req.method == "HEAD") {
        if (req.url !~ "^/(admin|account|cart|order|checkout|login|logout|register|_)") {
            set req.http.X-Public = "1";
        }
    }
}

sub vcl_recv {
    call is_public_page;

    if (req.method == "PURGE") {
        if (!client.ip ~ purgers) {
            return (synth(405));
        }
        if (req.http.Surrogate-Key) {
            set req.http.n-purged = xkey.purge(req.http.Surrogate-Key);
            return (synth(200, "purged " + req.http.n-purged));
        }
        return (purge);
    }

    if (req.http.X-Public == "1") {
        # The session cookie the backend sets on every response would make the
        # page uncacheable, and it means nothing to a visitor who has not
        # signed in.
        unset req.http.Cookie;
    } else {
        return (pass);
    }
}

sub vcl_backend_response {
    if (bereq.http.X-Public == "1" && beresp.http.Surrogate-Key) {
        unset beresp.http.Set-Cookie;
        set beresp.http.X-Cacheable = "yes";
    }
}

sub vcl_deliver {
    # Cache plumbing is nobody's business but ours.
    unset resp.http.Surrogate-Key;
    unset resp.http.Cache-Tag;
}
```

Without `xkey`, the same invalidation is a ban on the tag header:

```vcl
if (req.method == "BAN") {
    if (!client.ip ~ purgers) {
        return (synth(405));
    }
    ban("obj.http.Cache-Tag ~ " + req.http.X-Cache-Tag);
    return (synth(200, "banned"));
}
```

## Fastly and Cloudflare

Both read `Surrogate-Key` as it is written, and both purge by key through their
API. Nothing is needed in the VCL beyond stripping the session cookie on public
addresses, which Fastly does in `vcl_recv` exactly as above.

## Telling the proxy what to drop

The module works out which tags are stale: the page just published, the pages
drawing a menu that changed, the pages using a reusable block that was edited.
It hands them to every service tagged `thelia_cms.cache_purger`, and ships none
of its own. What a purge means depends on the proxy, on its address, on its
authentication and on whether it invalidates by key or by ban, and a wrong guess
is worse than doing nothing at all.

Writing one is a few lines. `docs/examples/VarnishCachePurger.php.example` is a
complete one; copy it into a project, rename it to `.php`, and the container
picks it up through the tag on the interface.

A purger that throws is logged and skipped: a CDN that cannot be reached must
not turn publishing a page into an error the editor has to understand.

Projects already running [FOSHttpCache](https://foshttpcache.readthedocs.io)
have proxy clients for Varnish, Fastly, Cloudflare and Nginx. An implementation
of `CachePurgerInterface` forwarding to one of those is three lines, and it is
the better road: that library has years of production behind it, and a second
client written here would have none.
