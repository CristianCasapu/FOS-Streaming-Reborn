the goal of this platform is to provide secured iptv services to subscribers(having a trial or subscription).
This whole platform will be more like a saas, a proxy(to protect the source) of streaming plarform using enterprise grade security, fast, with low consumption of resources.

Besides the existing elements/components/features, the hole platform will have:
- the header with the branding, nav menu, user profile, disconnect button
- the footer with the contact developer(see readme), link to donations(see readme)

*Mainly, there will be a few services that will open ports for communication with the exterior.
- web port will be served by the nginx + php-fpm + redis + memcached, behind services like cloudflare, sucuri, etc.
- rtmp port will serve to fetch(from source) and push streams between the nodes(load balancers) of the platform, served by custom built nginx with rtmp and flw modules + php-fpm and it will run as a built-in pm2 service
- streaming port will be serving the streams to subscribers thru out a dedicated port using the custom built nginx(mentioned above) + php-fpm (need to investigate if we need other extensions to this mechanism) and it will run as a built-in pm2 service
- there will be 5 main routes for communication, configurable in the .env
-- admin route served thru the web port
-- subscriber route served thru the web port
-- reseller route served thru the web port
-- streaming route served thru the streaming gateway
-- communication between the nodes served thru the web port(heartbit using custom built-in pm2 service) + streaming gateway to share the streams for load-balancing purposes

*The streams container will be the most compatible ones: mp4 + aac for live streams, and mkv for VOD
*The containers will likely have more than one tracks(main video, main audio, main subtitle, multiple audio and subtitles), for which, we will need the ffprobe service to run in a manner that will be able to know what profile to attach to each streaming source


The dashboard section will provide the administrators, supervisors and support staff with information about:
- the server health
- server resources usage stats
- services(user services, pm2 services) states
- security overview
- current state of the streams(counters)
- current state of active subscribers(counters with active subscriptions, active trials, expired subscriptions, about to expire subscriptions)
- recent activities
-quick actions


The streams management group will contain the following pages:
- streams management (this is going to be the sources(main source, backup source(json format with multiple sources for fallback in case the main is not available))), assignable to bouquets
- bouquets management (assignable to categories)
- categories management
- packages (assignable to subscriptions)
- streams profiles (used by ffprobe and ffmpeg to test and connect to the streams sources)
*Besides the existing states, there will be an extra state: `on demand`, this on demand state will have the stream on standby and will run using fast profile to serve the on demand stream to subscriber.
**The whole idea of streams is to be able to fetch them in buffer mode by the ffmpeg and serve the buffer to the subscribers.
**We will serve the ffmpeg streams buffers secured as described in `docs/guides/streaming_security.md`. This area is unknown by me and we will have to investigate it and come with a flawless plan to implement it.


The security of this platform will be manageable thru the advanced security ufw + fail2ban, and integration with services like cloudflare or sucuri or etc.


The admins is actually the staff which will contain multiple sections like:
-Roles management
-Staff management
*Thruout these interfaces, we will be able to manage staff that can manage the platform.
*There will be 3 main roles: Admin, Supervisor, Support
*We will audit each action of these types of roles, And the audit will be available to the admin role only.
*Only the admin role will be able to manage below roles and platform settings
*The supervisors will have one level lowed than admin(will not be able to manage admins or their kind)
*The support will have access and manage the dashboard, the streams, the subscribers, and see the security logs(for troubleshooting purposes)

***We will need to make sure that any page in the web interface can not be accessed without being authenticated***

***The subscribers will be able to access their subscriptions or trials streaming service using the access key attached to the subscription/trial.***

***We will make sure that all the gateways will be fully managed thru the settings page***
***Once set a port of a gateway, changing it will likely have an impact to the subscribers and their subscriptions, therefore, changing a port will throw a warn with confirmation***
***There will be no SEO, no robots, no sitemap for this platform, we do not want them to be indexed in any search engine***
***We will have directive that will prevent the ports scanners, crawlers and robots or bad intentioned mechanisms to sniff in and scan the gateways***
***Sniffing/scanning will automatically block permanently the ip of sniffing/scanning***