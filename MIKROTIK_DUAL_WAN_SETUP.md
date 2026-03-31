# MikroTik Dual-WAN Load Balancing and Failover Guide

This guide provides instructions to configure a MikroTik router for Dual-WAN Load Balancing (using PCC - Per Connection Classifier) and automatic Failover.

## Prerequisites
- **ISP 1** must be connected to `ether1`. Its gateway is `192.168.1.1`.
- **ISP 2** must be connected to `ether2`. Its gateway is `192.168.254.254`.

---

## Step 1: Core Routing and Load Balancing Configuration

If you haven't already, run the following script in the MikroTik terminal to configure the interfaces, mangle rules, routing tables, and firewall NAT.

```routeros
# 1. REMOVE ETHER2 FROM LAN BRIDGE AND ADD IT TO WAN LIST
/interface bridge port remove [find interface=ether2]
/interface list member add interface=ether2 list=WAN

# 2. CREATE ROUTING TABLES (Required in RouterOS v7)
/routing table
add name=to_WAN1 fib
add name=to_WAN2 fib

# 3. CONFIGURE FIREWALL MANGLE RULES FOR PCC LOAD BALANCING
/ip firewall mangle
# 3a. Accept local network traffic so it's not routed out to the internet
add chain=prerouting dst-address=192.168.88.0/24 action=accept
add chain=prerouting dst-address=10.10.10.0/24 action=accept

# 3b. Mark traffic coming IN from the ISPs to guarantee replies go out the same ISP
add chain=prerouting in-interface=ether1 connection-mark=no-mark action=mark-connection new-connection-mark=WAN1_conn passthrough=yes
add chain=prerouting in-interface=ether2 connection-mark=no-mark action=mark-connection new-connection-mark=WAN2_conn passthrough=yes

# 3c. PCC Load Balancing Rules (Split connections 50/50 based on both source and destination IPs for better balancing without breaking secure sites)
add chain=prerouting in-interface=bridge connection-mark=no-mark dst-address-type=!local per-connection-classifier=both-addresses:2/0 action=mark-connection new-connection-mark=WAN1_conn passthrough=yes
add chain=prerouting in-interface=bridge connection-mark=no-mark dst-address-type=!local per-connection-classifier=both-addresses:2/1 action=mark-connection new-connection-mark=WAN2_conn passthrough=yes
add chain=prerouting in-interface=bridge-hotspot connection-mark=no-mark dst-address-type=!local per-connection-classifier=both-addresses:2/0 action=mark-connection new-connection-mark=WAN1_conn passthrough=yes
add chain=prerouting in-interface=bridge-hotspot connection-mark=no-mark dst-address-type=!local per-connection-classifier=both-addresses:2/1 action=mark-connection new-connection-mark=WAN2_conn passthrough=yes

# 3d. Mark the routed packets to obey the connection marks above
add chain=prerouting connection-mark=WAN1_conn in-interface=bridge action=mark-routing new-routing-mark=to_WAN1 passthrough=yes
add chain=prerouting connection-mark=WAN2_conn in-interface=bridge action=mark-routing new-routing-mark=to_WAN2 passthrough=yes
add chain=prerouting connection-mark=WAN1_conn in-interface=bridge-hotspot action=mark-routing new-routing-mark=to_WAN1 passthrough=yes
add chain=prerouting connection-mark=WAN2_conn in-interface=bridge-hotspot action=mark-routing new-routing-mark=to_WAN2 passthrough=yes
add chain=output connection-mark=WAN1_conn action=mark-routing new-routing-mark=to_WAN1 passthrough=yes
add chain=output connection-mark=WAN2_conn action=mark-routing new-routing-mark=to_WAN2 passthrough=yes

# 4. CONFIGURE NAT FOR THE NEW WAN
/ip firewall nat
add action=masquerade chain=srcnat out-interface=ether2 comment="NAT for WAN2"

# 5. CONFIGURE ROUTES AND FAILOVER 
# *** USING ACTUAL ISP GATEWAY IPs: 192.168.1.1 (ISP1) and 192.168.254.254 (ISP2) ***
/ip route
# Routes for marked traffic (Primary)
add dst-address=0.0.0.0/0 gateway=192.168.1.1 routing-table=to_WAN1 check-gateway=ping
add dst-address=0.0.0.0/0 gateway=192.168.254.254 routing-table=to_WAN2 check-gateway=ping

# Routes for marked traffic (Backup/Cross-Failover)
# This is crucial so that "WAN1 marked traffic" can still escape out WAN2 when WAN1 is unplugged!
add dst-address=0.0.0.0/0 gateway=192.168.254.254 distance=2 routing-table=to_WAN1
add dst-address=0.0.0.0/0 gateway=192.168.1.1 distance=2 routing-table=to_WAN2

# Standard routes with Distance for Failover (If WAN1 dies, everything transparently fails over to WAN2)
add dst-address=0.0.0.0/0 gateway=192.168.1.1 distance=1 check-gateway=ping
add dst-address=0.0.0.0/0 gateway=192.168.254.254 distance=2 check-gateway=ping
```

---

## Step 2: Set up IP Addresses for `ether1` and `ether2` (DHCP)

If your ISP routers hand out IP addresses automatically (DHCP), you need to set up DHCP Clients on both interfaces, but **without default routes** (since the load balancing script handles the routing).

Run these commands:
```routeros
/ip dhcp-client
add interface=ether1 add-default-route=no disabled=no
add interface=ether2 add-default-route=no disabled=no
```
*(If your ISPs require Static IPs instead of DHCP, you add them manually via `/ip address add address=X.X.X.X/24 interface=ether1` instead.)*

---

## Step 3: Verify Your Setup

Based on the environment:
- **`ether1`** receives IP `192.168.1.2` and uses Gateway `192.168.1.1`
- **`ether2`** receives IP `192.168.254.101` and uses Gateway `192.168.254.254`

The routes generated in Step 1 already contain these exact IP addresses! You do not need to manually update them unless your ISPs change their modem IP ranges. You can verify they are active by navigating to **IP** -> **Routes** in Winbox, where you should see the 4 routes active and reachable.

---

## Step 4: Set Global DNS

To make sure the router can resolve domains regardless of which ISP is active, set a public DNS:
```routeros
/ip dns set servers=8.8.8.8,1.1.1.1 allow-remote-requests=yes
```

---

## Step 5: Test the Setup

1. **Load Balancing Test:** Connect multiple devices to your Hotspot or LAN. Go to Winbox -> **Interfaces** and watch the TX/RX traffic on `ether1` and `ether2`. You should see traffic being distributed across both.
2. **Failover Test:** Run a continuous ping on a connected computer:
   - Windows: `ping 8.8.8.8 -t`
   - Linux/macOS: `ping 8.8.8.8`
   
   Unplug the cable from `ether1`. The ping might drop once or twice, but it should resume as traffic automatically routes through `ether2`. Plug it back in, and unplug `ether2` to test the other direction.
